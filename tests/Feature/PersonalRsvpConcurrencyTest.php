<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\InvitationGroup;
use App\Models\User;
use App\Models\WebsiteDraft;
use App\Models\Wedding;
use App\Services\PersonalInvitationLink;
use App\Services\PersonalRsvp;
use App\Services\PersonalRsvpTransaction;
use App\Services\RsvpDelegationManager;
use App\Services\WeddingPublication;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\Support\TestDatabaseGuard;
use Tests\TestCase;

#[Group('mysql-concurrency')]
class PersonalRsvpConcurrencyTest extends TestCase
{
    private ?string $originalConnection = null;

    protected function setUp(): void
    {
        parent::setUp();
        $configuration = TestDatabaseGuard::disposableMySqlConfigurationFromProcess();
        if ($configuration === null) {
            $this->markTestSkipped('Set the explicit CMS_MYSQL_TEST_* disposable InnoDB environment; no schema is provisioned/reset.');
        }
        $database = $configuration['database'];
        $this->assertMatchesRegularExpression('/\Awedding_rsvp_cms_test(?:_[a-z0-9_]+)?\z/D', $database);
        $this->assertTrue($this->app->environment('testing'));
        $this->originalConnection = config('database.default');
        unset($configuration['read'], $configuration['write']);
        config(['database.connections.personal_mysql_test' => $configuration, 'database.connections.personal_mysql_blocker' => $configuration]);
        try {
            $connection = DB::connection('personal_mysql_test');
            $this->assertSame($database, $connection->selectOne('SELECT DATABASE() AS name')->name);
            foreach (['users', 'accounts', 'account_members', 'weddings', 'website_drafts', 'website_revisions', 'invitation_groups', 'guests', 'guest_invitation_links', 'rsvp_delegations', 'rsvp_responses'] as $table) {
                if (! $connection->getSchemaBuilder()->hasTable($table)) {
                    $this->markTestSkipped('The disposable database needs all existing and foundation migrations; none are applied by this test.');
                }
            }
            if (! $connection->getSchemaBuilder()->hasColumns('guests', ['rsvp_version', 'rsvp_access_version'])) {
                $this->markTestSkipped('Guest counters are absent from the disposable target.');
            }
            $engines = $connection->select('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (?, ?, ?, ?, ?, ?, ?)', [
                $database, 'weddings', 'invitation_groups', 'guests', 'guest_invitation_links', 'rsvp_delegations', 'rsvp_responses', 'account_members',
            ]);
            $this->assertCount(7, $engines);
            foreach ($engines as $table) {
                $this->assertSame('InnoDB', $table->ENGINE);
            }
            $connection->select('SELECT trx_mysql_thread_id FROM information_schema.INNODB_TRX LIMIT 0');
        } catch (QueryException|PDOException) {
            $this->markTestSkipped('Disposable InnoDB/inspection privileges are unavailable; no development records or schema changed.');
        }
        config(['database.default' => 'personal_mysql_test']);
    }

    protected function tearDown(): void
    {
        if ($this->originalConnection !== null) {
            config(['database.default' => $this->originalConnection]);
            DB::purge('personal_mysql_test');
            DB::purge('personal_mysql_blocker');
        }
        parent::tearDown();
    }

    public static function races(): array
    {
        $cases = ['issuance', 'independent', 'same-guest', 'designation', 'revoke', 'rotate', 'reassign', 'representative-batch', 'rename', 'retention',
            'delegation-revoke', 'unpublish', 'republish', 'rotate-revoke'];

        return array_combine($cases, array_map(fn (string $case): array => [$case], $cases));
    }

    #[DataProvider('races')]
    public function test_independent_workers_revalidate_permissions_versions_and_atomicity_after_real_lock_waits(string $case): void
    {
        [$owner, $wedding, $group, $guests] = DB::transaction(function (): array {
            $owner = User::factory()->create();
            $account = Account::create(['name' => 'Disposable personal RSVP race fixture']);
            $account->users()->attach($owner, ['role' => 'owner']);
            $wedding = $account->weddings()->create(['partner_one_name' => 'Synthetic Alex', 'partner_two_name' => 'Synthetic Jamie', 'wedding_date' => '2027-05-12', 'public_slug' => fake()->uuid()]);
            $this->createDraft($owner, $wedding);
            $publisher = app(WeddingPublication::class);
            $state = $publisher->summary($wedding->fresh());
            $publisher->publish($wedding, ['draft_version' => $state['draftVersion'], 'publication_version' => 0, 'snapshot_token' => $state['snapshotToken']], $owner);
            $group = InvitationGroup::factory()->for($wedding)->withGuests(4)->create();

            return [$owner, $wedding->fresh(), $group, $group->guests->all()];
        });
        $blocker = DB::connection('personal_mysql_blocker');
        $processes = [];
        try {
            $command = ['op' => 'generate', 'owner' => $owner->id, 'wedding' => $wedding->id, 'group' => $group->id, 'guest' => $guests[0]->id];
            $commands = [$command, $command];
            if ($case === 'retention') {
                $commands[1]['op'] = 'delete';
            }
            if (! in_array($case, ['issuance', 'retention'], true)) {
                $token = app(PersonalInvitationLink::class)->execute($owner, $wedding, $group, $guests[0], 'generate', 0, 0)['token'];
                if (in_array($case, ['independent', 'designation', 'reassign'], true)) {
                    $otherToken = app(PersonalInvitationLink::class)->execute($owner, $wedding, $group, $guests[1], 'generate', 0, 0)['token'];
                }
                if (in_array($case, ['reassign', 'representative-batch', 'delegation-revoke'], true)) {
                    app(RsvpDelegationManager::class)->assign($owner, $wedding, $group, $guests[2], $guests[0], [$guests[0]->id => 1, $guests[2]->id => 0], 0);
                }
                $state = app(PersonalRsvp::class)->read($token);
                $responses = array_map(fn (array $guest): array => ['guest' => $guest['reference'], 'version' => $guest['version'], 'status' => 'attending'], $state['guests']);
                $submission = ['op' => 'submit', 'token' => $token, 'payload' => ['access_version' => $state['access_version'], 'responses' => $responses]];
                $commands = [$submission, $submission];
                $commands[1]['payload']['responses'][0]['status'] = 'declined';
                if ($case === 'representative-batch') {
                    $commands[0]['payload']['responses'][1]['status'] = 'declined';
                }
                if ($case === 'independent') {
                    $otherState = app(PersonalRsvp::class)->read($otherToken);
                    $commands[1] = ['op' => 'submit', 'token' => $otherToken, 'payload' => ['access_version' => $otherState['access_version'], 'responses' => [
                        ['guest' => $otherState['guests'][0]['reference'], 'version' => 0, 'status' => 'declined'],
                    ]]];
                } elseif ($case === 'designation') {
                    foreach ([0, 1] as $index) {
                        $commands[$index] = [...$command, 'op' => 'assign', 'dependent' => $guests[2]->id, 'representative' => $guests[$index]->id,
                            'versions' => [$guests[2]->id => 0, $guests[$index]->id => 1]];
                    }
                } elseif ($case === 'rotate-revoke') {
                    $commands = [[...$command, 'op' => 'regenerate', 'link_version' => 1, 'access_version' => 1],
                        [...$command, 'op' => 'revoke', 'link_version' => 1, 'access_version' => 1]];
                }
            }
            $blocker->beginTransaction();
            $blocker->table('weddings')->where('id', $wedding->id)->lockForUpdate()->first();
            foreach ($commands as $input) {
                $process = new Process([PHP_BINARY, '-r', $this->workerCode()], base_path(), $this->workerEnvironment());
                $process->setInput(json_encode($input))->setTimeout(25)->start();
                $processes[] = $process;
            }
            $this->waitFor(fn (): bool => count(array_filter($processes, fn (Process $process): bool => preg_match('/READY (\d+)/', $process->getOutput()) === 1)) === 2);
            $ids = [];
            foreach ($processes as $process) {
                preg_match('/READY (\d+)/', $process->getOutput(), $match);
                $ids[] = (int) $match[1];
            }
            $this->assertCount(2, array_unique($ids));
            $this->assertNotContains((int) $blocker->selectOne('SELECT CONNECTION_ID() AS id')->id, $ids);
            $this->waitFor(fn (): bool => count(DB::select('SELECT trx_mysql_thread_id FROM information_schema.INNODB_TRX WHERE trx_state = ? AND trx_mysql_thread_id IN (?, ?)', ['LOCK WAIT', ...$ids])) === 2, 'Expected waiting worker connections: '.implode(', ', $ids));
            if (in_array($case, ['revoke', 'rotate', 'reassign', 'rename', 'delegation-revoke', 'unpublish', 'republish'], true)) {
                config(['database.default' => 'personal_mysql_blocker']);
                try {
                    if ($case === 'rename') {
                        config(['rsvp.mode' => 'personal']);
                        app(PersonalRsvpTransaction::class)->run($wedding->id, $group->id, function ($locked, $members) use ($guests): void {
                            app(PersonalRsvpTransaction::class)->member($members, $guests[0]->id)->update(['name' => 'Renamed race fixture']);
                        }, $owner);
                    } elseif ($case === 'reassign') {
                        app(RsvpDelegationManager::class)->assign($owner, $wedding, $group, $guests[2], $guests[1], [$guests[0]->id => 2, $guests[1]->id => 1, $guests[2]->id => 1], 0);
                    } elseif ($case === 'delegation-revoke') {
                        app(RsvpDelegationManager::class)->revoke($owner, $wedding, $group, $guests[2], [$guests[0]->id => 2, $guests[2]->id => 1], 0);
                    } elseif ($case === 'unpublish') {
                        app(WeddingPublication::class)->unpublish($wedding, 1, $owner);
                    } elseif ($case === 'republish') {
                        $locked = Wedding::query()->findOrFail($wedding->id);
                        $locked->update(['partner_one_name' => 'Republished concurrency fixture']);
                        $publisher = app(WeddingPublication::class);
                        $state = $publisher->summary($locked->fresh());
                        $publisher->publish($locked, ['draft_version' => $state['draftVersion'], 'publication_version' => $state['publicationVersion'], 'snapshot_token' => $state['snapshotToken']], $owner);
                    } else {
                        app(PersonalInvitationLink::class)->execute($owner, $wedding, $group, $guests[0], $case === 'rotate' ? 'regenerate' : 'revoke', 1, 1);
                    }
                } finally {
                    config(['database.default' => 'personal_mysql_test']);
                    config(['rsvp.mode' => 'personal']);
                }
            }
            $blocker->commit();
            $codes = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), 'Isolated service worker failed; no bearer input is included in diagnostics.');
                $this->assertSame(1, preg_match('/RESULT (\d{3})/', $process->getOutput(), $result));
                $codes[] = (int) $result[1];
            }
            sort($codes);
            $expected = match ($case) {
                'independent' => [200, 200], 'revoke', 'rotate', 'unpublish' => [404, 404], 'reassign', 'rename', 'delegation-revoke' => [422, 422],
                'retention' => DB::table('guests')->where('id', $guests[0]->id)->exists() ? [200, 422] : [200, 404], default => [200, 422],
            };
            $this->assertSame($expected, $codes);
            $this->assertSame($case === 'retention' ? (int) DB::table('guests')->where('id', $guests[0]->id)->exists() : ($case === 'designation' || $case === 'independent' || $case === 'reassign' ? 2 : 1), DB::table('guest_invitation_links')->whereIn('guest_id', array_map(fn ($guest) => $guest->id, $guests))->count());
            $responses = DB::table('rsvp_responses')->whereIn('guest_id', array_map(fn ($guest) => $guest->id, $guests))->get();
            $this->assertCount(in_array($case, ['independent', 'representative-batch'], true) ? 2 : (in_array($case, ['same-guest', 'republish'], true) ? 1 : 0), $responses);
            if ($case === 'designation') {
                $this->assertSame(1, DB::table('rsvp_delegations')->where('dependent_guest_id', $guests[2]->id)->count());
            }
            if ($case === 'representative-batch') {
                $this->assertSame(['attending', 'declined'], $responses->pluck('status')->sort()->values()->all());
            }
            if ($case === 'delegation-revoke') {
                $this->assertNull(DB::table('rsvp_delegations')->where('dependent_guest_id', $guests[2]->id)->sole()->representative_guest_id);
            }
            if (in_array($case, ['unpublish', 'republish'], true)) {
                $fresh = $wedding->fresh();
                $this->assertSame(2, $fresh->publication_version);
                if ($case === 'unpublish') {
                    $this->assertNull($fresh->published_revision_id);
                } else {
                    $this->assertSame('Republished concurrency fixture', $fresh->publishedRevision->wedding_facts['partner_one_name']);
                }
            }
            if ($case === 'rotate-revoke') {
                $this->assertSame(2, $guests[0]->fresh()->invitationLink->version);
                $this->assertSame(2, $guests[0]->fresh()->rsvp_access_version);
            }
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            if ($blocker->transactionLevel() > 0) {
                $blocker->rollBack();
            }
            DB::transaction(function () use ($owner, $wedding, $guests): void {
                $ids = array_map(fn ($guest) => $guest->id, $guests);
                DB::table('guest_invitation_links')->whereIn('guest_id', $ids)->delete();
                DB::table('rsvp_delegations')->whereIn('dependent_guest_id', $ids)->delete();
                DB::table('rsvp_responses')->whereIn('guest_id', $ids)->delete();
                DB::table('weddings')->where('id', $wedding->id)->update(['published_revision_id' => null]);
                DB::table('website_revisions')->where('wedding_id', $wedding->id)->delete();
                DB::table('website_drafts')->where('wedding_id', $wedding->id)->delete();
                DB::table('weddings')->where('id', $wedding->id)->delete();
                DB::table('accounts')->where('id', $wedding->account_id)->delete();
                DB::table('users')->where('id', $owner->id)->delete();
            });
        }
    }

    private function waitFor(callable $condition, string $message = 'Independent workers did not reach the expected InnoDB lock barrier.'): void
    {
        $deadline = microtime(true) + 10;
        do {
            if ($condition()) {
                return;
            }
            // Allow the transaction metadata snapshot to refresh between observations.
            usleep(150000);
        } while (microtime(true) < $deadline);
        $states = DB::select('SELECT p.ID AS id, t.trx_state AS state FROM information_schema.PROCESSLIST p LEFT JOIN information_schema.INNODB_TRX t ON t.trx_mysql_thread_id=p.ID WHERE p.DB = ?', [getenv('CMS_MYSQL_TEST_DATABASE')]);
        $this->fail($message.' Observed test connections: '.json_encode($states));
    }

    protected function createDraft(User $owner, Wedding $wedding): void
    {
        $wedding->websiteDraft()->create(['schema_version' => 1, 'lock_version' => 0, 'content' => WebsiteDraft::defaultContent()]);
    }

    private function workerEnvironment(): array
    {
        $configuration = config('database.connections.personal_mysql_test');
        $prefix = preg_replace('/^[A-Za-z]:/', '', str_replace('\\', '/', sys_get_temp_dir())).'/personal-rsvp-worker-'.bin2hex(random_bytes(8));

        return array_merge(TestDatabaseGuard::workerEnvironment($configuration), [
            'CMS_EDITOR_PREVIEW_ENABLED' => config('cms_editor.preview_enabled') === true ? 'true' : 'false',
            'CMS_V2_PUBLISHING_ENABLED' => config('cms_editor.publishing_enabled') === true ? 'true' : 'false',
            'APP_CONFIG_CACHE' => $prefix.'-config.php', 'APP_ROUTES_CACHE' => $prefix.'-routes.php', 'SESSION_DRIVER' => 'array', 'SESSION_PATH' => '/', 'CACHE_STORE' => 'array', 'LOG_CHANNEL' => 'null',
        ]);
    }

    private function workerCode(): string
    {
        return <<<'PHP'
require 'vendor/autoload.php';
try {
    $database = getenv('CMS_MYSQL_TEST_DATABASE');
    if (getenv('APP_ENV') !== 'testing' || getenv('CMS_MYSQL_TEST_ENABLED') !== 'true' || !preg_match('/\Awedding_rsvp_cms_test(?:_[a-z0-9_]+)?\z/D', $database ?: '') || getenv('DB_DATABASE') !== $database || getenv('DB_HOST') !== getenv('CMS_MYSQL_TEST_HOST') || getenv('DB_PORT') !== getenv('CMS_MYSQL_TEST_PORT') || getenv('DB_USERNAME') !== getenv('CMS_MYSQL_TEST_USERNAME') || getenv('DB_PASSWORD') !== getenv('CMS_MYSQL_TEST_PASSWORD')) { exit(90); }
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== $database || config('database.connections.mysql.url') !== null || Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() AS db')->db !== $database) { exit(91); }
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    echo 'READY '.Illuminate\Support\Facades\DB::selectOne('SELECT CONNECTION_ID() AS id')->id."\n";
    fflush(STDOUT);
    if ($input['op'] === 'submit') {
        app(App\Services\PersonalRsvp::class)->submit($input['token'], $input['payload']);
    } else {
        $owner = App\Models\User::findOrFail($input['owner']);
        $wedding = App\Models\Wedding::findOrFail($input['wedding']);
        $group = App\Models\InvitationGroup::findOrFail($input['group']);
        if ($input['op'] === 'delete') {
            config(['rsvp.mode' => 'personal']);
            app(App\Services\PersonalRsvpTransaction::class)->run($wedding->id, $group->id, function ($locked, $members) use ($input): void {
                app(App\Services\PersonalRsvpTransaction::class)->member($members, $input['guest'])->delete();
            }, $owner);
        } elseif (in_array($input['op'], ['generate', 'regenerate', 'revoke'], true)) {
            app(App\Services\PersonalInvitationLink::class)->execute($owner, $wedding, $group, App\Models\Guest::findOrFail($input['guest']), $input['op'], $input['link_version'] ?? 0, $input['access_version'] ?? 0);
        } else {
            app(App\Services\RsvpDelegationManager::class)->assign($owner, $wedding, $group, App\Models\Guest::findOrFail($input['dependent']), App\Models\Guest::findOrFail($input['representative']), $input['versions'], 0);
        }
    }
    echo "RESULT 200\n";
} catch (Illuminate\Validation\ValidationException) { echo "RESULT 422\n";
} catch (Illuminate\Database\Eloquent\ModelNotFoundException) { echo "RESULT 404\n";
} catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) { echo 'RESULT '.$error->getStatusCode()."\n";
} catch (Throwable) { echo "RESULT 500\n"; }
PHP;
    }
}
