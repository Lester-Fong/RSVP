<?php

namespace Tests\Feature\Weddings;

use App\Models\Account;
use App\Models\InvitationGroup;
use App\Models\User;
use App\Models\WebsiteDraft;
use App\Services\InvitationLink;
use App\Services\WeddingHeroPhotoProcessor;
use App\Services\WeddingPublication;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('mysql-concurrency')]
class WeddingDraftConcurrencyTest extends TestCase
{
    private ?string $originalConnection = null;

    protected function setUp(): void
    {
        parent::setUp();

        $database = getenv('CMS_MYSQL_TEST_DATABASE') ?: '';

        if ($database === '') {
            $this->markTestSkipped('Set CMS_MYSQL_TEST_DATABASE to an existing disposable wedding_rsvp_cms_test database; no database is created or reset.');
        }

        $this->assertMatchesRegularExpression('/\Awedding_rsvp_cms_test(?:_[a-z0-9_]+)?\z/D', $database, 'Concurrency tests refuse development databases.');
        $this->assertTrue($this->app->environment('testing'), 'MySQL concurrency tests require APP_ENV=testing.');
        $this->originalConnection = config('database.default');
        $configuration = config('database.connections.mysql');
        unset($configuration['read'], $configuration['write']);
        $configuration['url'] = null;
        $configuration['database'] = $database;
        config(['database.connections.cms_mysql_test' => $configuration, 'database.connections.cms_mysql_blocker' => $configuration]);

        try {
            $connection = DB::connection('cms_mysql_test');
            $this->assertSame($database, $connection->selectOne('SELECT DATABASE() AS name')->name);

            foreach (['users', 'accounts', 'account_members', 'weddings', 'website_drafts', 'website_revisions'] as $table) {
                if (! $connection->getSchemaBuilder()->hasTable($table)) {
                    $this->markTestSkipped('The disposable MySQL database needs the existing project migrations; these tests never provision or reset schema.');
                }
            }

            $engines = $connection->select('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (?, ?)', [$database, 'weddings', 'website_drafts']);
            $this->assertCount(2, $engines);

            foreach ($engines as $table) {
                $this->assertSame('InnoDB', $table->ENGINE);
            }

            $connection->select('SELECT trx_mysql_thread_id FROM information_schema.INNODB_TRX LIMIT 0');
        } catch (QueryException|PDOException) {
            $this->markTestSkipped('Disposable MySQL is unavailable, or its test user lacks schema/INNODB_TRX inspection privileges. No databases were created or reset.');
        }

        config(['database.default' => 'cms_mysql_test']);
    }

    protected function tearDown(): void
    {
        if ($this->originalConnection !== null) {
            config(['database.default' => $this->originalConnection]);
            DB::purge('cms_mysql_test');
            DB::purge('cms_mysql_blocker');
        }

        parent::tearDown();
    }

    /** @return array<string, array<int, int|bool|string>> */
    public static function draftVersions(): array
    {
        return ['simultaneous first saves' => [0, false], 'simultaneous existing draft updates' => [1, false],
            'simultaneous first photo saves' => [0, true], 'simultaneous photo replacements' => [1, true],
            'simultaneous first publications' => [1, false, true], 'simultaneous republications' => [2, false, true],
            'simultaneous link generation' => [0, false, false, 'generate'], 'simultaneous link regeneration' => [1, false, false, 'regenerate']];
    }

    #[DataProvider('draftVersions')]
    public function test_same_version_concurrent_requests_allow_exactly_one_complete_save(int $expectedVersion, bool $withPhoto, bool $publishing = false, string|false $links = false): void
    {
        $owner = User::factory()->create();
        $account = null;
        $wedding = null;
        $processes = [];
        $blocker = DB::connection('cms_mysql_blocker');
        $photoRoot = null;
        $photoBytes = [];
        $oldPhoto = null;
        $group = null;
        $oldHash = null;

        try {
            $account = Account::create(['name' => 'Disposable CMS concurrency test']);
            $account->users()->attach($owner, ['role' => 'owner']);
            $wedding = $account->weddings()->create([
                'partner_one_name' => 'Concurrency', 'partner_two_name' => 'Test',
                'public_slug' => 'cms-concurrency-'.Str::uuid(),
                'wedding_date' => '2027-05-12',
            ]);
            $originalWedding = $wedding->fresh()->getAttributes();

            if ($withPhoto) {
                if (! DB::connection()->getSchemaBuilder()->hasColumn('website_drafts', 'hero_photo_path')) {
                    $this->markTestSkipped('The disposable test database needs the additive hero-photo migration.');
                }
                $photoRoot = sys_get_temp_dir().'/rsvp-cms-photos-'.Str::uuid();
                File::makeDirectory($photoRoot);
                config(['filesystems.disks.wedding_photos.root' => $photoRoot]);
                Storage::forgetDisk('wedding_photos');
                $fixture = require base_path('tests/Fixtures/wedding-photo.php');
                $photoBytes = [$fixture(1), $fixture(2)];
            }

            if (($expectedVersion === 1 && ! $links) || $publishing) {
                $draft = WebsiteDraft::factory()->for($wedding)->create();
                if ($withPhoto) {
                    $oldPhoto = 'accounts/'.$account->id.'/weddings/'.$wedding->id.'/hero/'.Str::uuid().'.webp';
                    Storage::disk('wedding_photos')->put($oldPhoto, app(WeddingHeroPhotoProcessor::class)->normalize(UploadedFile::fake()->createWithContent('initial.jpg', $photoBytes[0])));
                    $draft->update(['hero_photo_disk' => 'wedding_photos', 'hero_photo_path' => $oldPhoto]);
                }
            }

            $publicationPayload = null;
            if ($links) {
                if (! DB::connection()->getSchemaBuilder()->hasColumn('invitation_groups', 'invitation_link_version')) {
                    $this->markTestSkipped('The disposable database needs the additive guest and invitation-link migrations.');
                }
                $group = InvitationGroup::factory()->for($wedding)->withGuests()->create();
                if ($links === 'regenerate') {
                    app(InvitationLink::class)->execute($wedding, $group, 'generate', 0);
                    $oldHash = $group->fresh()->invitation_token_hash;
                }
            }
            if ($publishing) {
                $publication = app(WeddingPublication::class);
                if ($expectedVersion === 2) {
                    $publication->publish($wedding, ['draft_version' => 1, 'publication_version' => 0, 'snapshot_token' => $publication->fingerprint($wedding, $draft)]);
                    $content = $draft->content;
                    $content['introduction_message'] = 'New saved publication';
                    $draft->update(['lock_version' => 2, 'content' => $content]);
                }
                $publicationPayload = ['draft_version' => $expectedVersion, 'publication_version' => $expectedVersion - 1,
                    'snapshot_token' => $publication->fingerprint($wedding->fresh(), $draft->fresh())];
            }

            $contents = [
                ['introduction_message' => 'Writer A introduction', 'closing_message' => 'Writer A closing', 'sections' => ['ceremony' => false, 'reception' => true, 'closing' => true]],
                ['introduction_message' => 'Writer B introduction', 'closing_message' => 'Writer B closing', 'sections' => ['ceremony' => true, 'reception' => false, 'closing' => false]],
            ];

            $blocker->beginTransaction();
            $blocker->table($links ? 'invitation_groups' : 'weddings')->where('id', $links ? $group->id : $wedding->id)->lockForUpdate()->first();

            foreach ($contents as $index => $content) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/save-wedding-draft.php')], base_path(), $this->workerEnvironment(), json_encode([
                    'owner_id' => $owner->id, 'account_id' => $account->id, 'wedding_id' => $wedding->id,
                    'content' => $content, 'draft_version' => $expectedVersion,
                    'photo_root' => $photoRoot, 'photo' => $withPhoto ? base64_encode($photoBytes[$index]) : null,
                    'operation' => $links ? 'link' : ($publishing ? 'publish' : 'draft'), 'publication' => $publicationPayload,
                    'group_id' => $group?->id, 'link_action' => $links, 'link_version' => $expectedVersion,
                ], JSON_THROW_ON_ERROR));
                $process->setTimeout(30);
                $processes[] = $process;
                $process->start();
            }

            $this->waitFor(function () use ($processes): bool {
                return count(array_filter($processes, fn (Process $process): bool => preg_match('/^READY \d+\r?$/m', $process->getOutput()) === 1)) === 2;
            }, 'Both independent request workers must connect before the lock is released.');

            $connectionIds = [];

            foreach ($processes as $process) {
                preg_match('/^READY (\d+)\r?$/m', $process->getOutput(), $matches);
                $connectionIds[] = (int) $matches[1];
            }

            $this->assertCount(2, array_unique($connectionIds));
            $this->assertNotContains((int) $blocker->selectOne('SELECT CONNECTION_ID() AS id')->id, $connectionIds);

            $this->waitFor(function () use ($connectionIds): bool {
                return count(DB::select('SELECT trx_mysql_thread_id FROM information_schema.INNODB_TRX WHERE trx_state = ? AND trx_mysql_thread_id IN (?, ?)', ['LOCK WAIT', ...$connectionIds])) === 2;
            }, 'Both HTTP requests must actually wait on the same InnoDB row lock; sequential saves cannot satisfy this test.');

            $blocker->commit();
            $results = [];

            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), 'Request worker failed: '.$process->getErrorOutput());
                $this->assertSame(1, preg_match('/^RESULT (.+)$/m', $process->getOutput(), $matches));
                $results[] = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
            }

            $statuses = array_column($results, 'status');
            sort($statuses);
            $this->assertSame([$links ? 200 : 302, 422], $statuses);
            $winner = $results[0]['status'] === ($links ? 200 : 302) ? 0 : 1;
            $loser = 1 - $winner;
            if ($links) {
                $this->assertSame('This invitation link changed in another window. Reload the guest list before trying again.', $results[$loser]['body']['errors']['invitation_link'][0]);
                $saved = $group->fresh();
                $this->assertSame($expectedVersion + 1, $saved->invitation_link_version);
                $this->assertSame(hash('sha256', $saved->invitation_token), $saved->invitation_token_hash);
                $this->assertNotSame($oldHash, $saved->invitation_token_hash);
                $this->assertSame(1, $saved->guests()->count());
                $this->assertSame(['status' => 'active', 'version' => $expectedVersion + 1], $results[$winner]['body']);
                $this->assertSame($originalWedding, $wedding->fresh()->getAttributes());

                return;
            }
            $this->assertSame($publishing ? 'Publication changed in another window. Reload before trying again.' : 'This draft was saved in another window. Copy your changes, then reload before saving again.', $results[$loser]['body']['errors'][$publishing ? 'publication' : 'draft_version'][0]);

            $this->assertSame(1, $wedding->websiteDraft()->count());
            $draft = $wedding->websiteDraft()->sole();
            $this->assertSame($publishing ? $expectedVersion : $expectedVersion + 1, $draft->lock_version);
            $this->assertSame(1, $draft->schema_version);
            if ($publishing) {
                $this->assertSame($expectedVersion, $wedding->websiteRevisions()->count());
                $this->assertSame(Arr::sortRecursive($draft->content), Arr::sortRecursive($wedding->fresh()->publishedRevision->content));
                $this->assertSame($expectedVersion, $wedding->fresh()->publication_version);
                $this->assertSame(Arr::except($originalWedding, ['published_revision_id', 'publication_version', 'updated_at']), Arr::except($wedding->fresh()->getAttributes(), ['published_revision_id', 'publication_version', 'updated_at']));
            } else {
                $this->assertSame(Arr::sortRecursive($contents[$winner]), Arr::sortRecursive($draft->content));
                $this->assertSame($originalWedding, $wedding->fresh()->getAttributes());
            }
            if ($withPhoto) {
                $storage = Storage::disk('wedding_photos');
                $this->assertSame('wedding_photos', $draft->hero_photo_disk);
                $storage->assertCount('accounts', 1, true);
                $this->assertSame(app(WeddingHeroPhotoProcessor::class)->normalize(UploadedFile::fake()->createWithContent('winner.jpg', $photoBytes[$winner])), $storage->get($draft->hero_photo_path));
                if ($oldPhoto !== null) {
                    $storage->assertMissing($oldPhoto);
                }
            }
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(0);
                }
            }

            if ($blocker->transactionLevel() > 0) {
                $blocker->rollBack();
            }

            if ($publishing && $wedding !== null) {
                $wedding->published_revision_id = null;
                $wedding->save();
            }
            $wedding?->delete();
            $account?->delete();
            $owner->delete();
            if ($photoRoot !== null && realpath(dirname($photoRoot)) === realpath(sys_get_temp_dir()) && str_starts_with(basename($photoRoot), 'rsvp-cms-photos-')) {
                File::deleteDirectory($photoRoot);
            }
        }
    }

    private function waitFor(callable $condition, string $message): void
    {
        $deadline = microtime(true) + 10;

        do {
            if ($condition()) {
                return;
            }

            usleep(20000);
        } while (microtime(true) < $deadline);

        $this->fail($message);
    }

    /** @return array<string, string|false> */
    private function workerEnvironment(): array
    {
        $configuration = config('database.connections.cms_mysql_test');
        $cachePrefix = sys_get_temp_dir().'/rsvp-cms-concurrency-'.Str::uuid();

        return [
            'APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => $cachePrefix.'-config.php', 'APP_ROUTES_CACHE' => $cachePrefix.'-routes.php',
            'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $configuration['database'], 'DB_URL' => '(null)',
            'DB_HOST' => (string) $configuration['host'], 'DB_PORT' => (string) $configuration['port'],
            'DB_USERNAME' => (string) $configuration['username'], 'DB_PASSWORD' => (string) $configuration['password'],
            'DB_SOCKET' => (string) $configuration['unix_socket'], 'CMS_MYSQL_TEST_DATABASE' => $configuration['database'],
            'SESSION_DRIVER' => 'array', 'SESSION_PATH' => '/', 'CACHE_STORE' => 'array', 'LOG_CHANNEL' => 'null',
        ];
    }
}
