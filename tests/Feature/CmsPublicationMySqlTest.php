<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\InvitationGroup;
use App\Models\User;
use App\Services\CmsDraft;
use App\Services\CmsEditorPreview;
use App\Services\UploadWeddingImage;
use App\Services\WeddingPublication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\Support\TestDatabaseGuard;
use Tests\TestCase;

#[Group('mysql-concurrency')]
class CmsPublicationMySqlTest extends TestCase
{
    public static function schedules(): array
    {
        return array_map(fn (string $name): array => [$name], ['publish', 'save-publish', 'republish-unpublish', 'credential-publish', 'media-unpublish', 'facts-publish', 'rollback-publish']);
    }

    #[DataProvider('schedules')]
    public function test_independent_http_workers_serialize_the_cms_publication_boundary(string $schedule): void
    {
        $configuration = TestDatabaseGuard::disposableMySqlConfigurationFromProcess();
        if ($configuration === null) {
            $this->markTestSkipped('Set the explicit CMS_MYSQL_TEST_* disposable InnoDB environment and PROCESS inspection privilege.');
        }
        $database = $configuration['database'];
        $this->assertMatchesRegularExpression('/\Awedding_rsvp_cms_test(?:_[a-z0-9_]+)?\z/D', $database);
        $this->assertTrue($this->app->environment('testing'));
        unset($configuration['read'], $configuration['write']);
        $this->assertContains($configuration['host'], ['127.0.0.1', 'localhost', '::1']);
        config(['database.connections.cms_publish_mysql' => $configuration, 'database.connections.cms_publish_blocker' => $configuration]);
        $original = config('database.default');
        $root = sys_get_temp_dir().'/rsvp-cms-media-'.Str::uuid();
        $processes = [];
        $blocker = DB::connection('cms_publish_blocker');
        try {
            $connection = DB::connection('cms_publish_mysql');
            $this->assertSame($database, $connection->selectOne('SELECT DATABASE() AS name')->name);
            foreach (['weddings', 'website_drafts', 'website_revisions', 'wedding_media_assets', 'website_revision_media'] as $table) {
                $this->assertSame('InnoDB', $connection->selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$database, $table])->engine);
            }
            $connection->select('SELECT trx_mysql_thread_id FROM information_schema.INNODB_TRX LIMIT 0');
            config(['database.default' => 'cms_publish_mysql', 'cms_editor.preview_enabled' => true, 'cms_editor.publishing_enabled' => true,
                'filesystems.disks.wedding_media.root' => $root]);
            File::makeDirectory($root);
            $owner = User::factory()->create();
            $account = Account::create(['name' => 'Disposable DEL03b contention']);
            $account->users()->attach($owner, ['role' => 'owner']);
            $wedding = $account->weddings()->create(['partner_one_name' => 'Race Alex', 'partner_two_name' => 'Race Jamie', 'public_slug' => (string) Str::uuid(),
                'wedding_date' => '2027-05-12', 'wedding_time' => '16:30', 'timezone' => 'Asia/Manila']);
            $references = [];
            for ($index = 0; $index < 2; $index++) {
                $asset = app(UploadWeddingImage::class)->upload($owner, $wedding, UploadedFile::fake()->image('race'.$index.'.png', 48, 64));
                $references[] = ['asset_id' => $asset['id'], 'alt' => 'Synthetic photograph', 'focal' => 'center', 'crop' => 'natural'];
            }
            $source = CmsEditorPreview::document();
            $source['content']['photo_slots']['hero'] = $references[0];
            $source['content']['sections'][4]['enabled'] = false;
            $source['content']['sections'][4]['data']['items'] = [['id' => (string) Str::uuid(), 'enabled' => false, 'image' => $references[1]]];
            app(CmsDraft::class)->save($owner, $wedding, $source, 0);
            $publication = app(WeddingPublication::class);
            if (in_array($schedule, ['republish-unpublish', 'media-unpublish'], true)) {
                $publication->publish($wedding, $this->payload($publication->summary($wedding->fresh())), $owner);
            }
            $changed = $source;
            $changed['content']['photo_slots']['hero'] = $references[1];
            if ($schedule === 'republish-unpublish') {
                app(CmsDraft::class)->save($owner, $wedding, $changed, 1);
            }
            $expected = $this->payload($publication->summary($wedding->fresh()));
            $publishing = ['operation' => 'publish', 'publication' => $expected];
            $other = match ($schedule) {
                'save-publish' => ['operation' => 'cms_draft', 'document' => $changed, 'draft_version' => 1],
                'republish-unpublish', 'media-unpublish' => ['operation' => 'unpublish', 'publication_version' => 1],
                'facts-publish' => ['operation' => 'facts', 'facts' => ['partner_one_name' => 'Race Alex', 'partner_two_name' => 'Race Jamie',
                    'wedding_date' => '2027-05-12', 'wedding_time' => '16:30', 'timezone' => 'Asia/Tokyo']],
                default => $publishing,
            };
            if ($schedule === 'credential-publish') {
                $group = InvitationGroup::factory()->for($wedding)->withGuests()->create();
                $other = ['operation' => 'link', 'group_id' => $group->id, 'guest_id' => $group->guests()->sole()->id, 'link_action' => 'generate', 'link_version' => 0];
            }
            if ($schedule === 'media-unpublish') {
                $publishing = ['operation' => 'media', 'slug' => $wedding->public_slug, 'asset' => $references[0]['asset_id']];
            }
            if ($schedule === 'rollback-publish') {
                $other['fail_binding'] = true;
            }
            $blocker->beginTransaction();
            $blocker->table('weddings')->where('id', $wedding->id)->lockForUpdate()->first();
            foreach ($schedule === 'rollback-publish' ? [$other, $publishing] : [$publishing, $other] as $operation) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/save-wedding-draft.php')], base_path(), array_merge(
                    TestDatabaseGuard::workerEnvironment($configuration), [
                        'APP_DEBUG' => 'false', 'APP_CONFIG_CACHE' => $root.'-config.php', 'APP_ROUTES_CACHE' => $root.'-routes.php',
                        'CMS_EDITOR_PREVIEW_ENABLED' => 'true', 'CMS_V2_PUBLISHING_ENABLED' => 'true',
                        'SESSION_DRIVER' => 'array', 'SESSION_PATH' => '/', 'CACHE_STORE' => 'array', 'LOG_CHANNEL' => 'null',
                    ]), json_encode($operation + ['owner_id' => $owner->id, 'account_id' => $account->id, 'wedding_id' => $wedding->id, 'media_root' => $root], JSON_THROW_ON_ERROR));
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
                if ($schedule === 'rollback-publish' && count($processes) === 1) {
                    $this->waitFor(fn (): bool => preg_match('/^READY (\d+)/m', $process->getOutput()) === 1, 'The failure worker must connect first.');
                    preg_match('/^READY (\d+)/m', $process->getOutput(), $first);
                    $this->waitFor(fn (): bool => count($connection->select('SELECT trx_mysql_thread_id FROM information_schema.INNODB_TRX WHERE trx_state = ? AND trx_mysql_thread_id = ?', ['LOCK WAIT', (int) $first[1]])) === 1,
                        'The failure worker must acquire its place in the actual lock wait before the successful publisher starts.');
                }
            }
            $this->waitFor(fn (): bool => count(array_filter($processes, fn (Process $process): bool => preg_match('/^READY \d+\r?$/m', $process->getOutput()) === 1)) === 2,
                'Both independent HTTP processes must connect.');
            $ids = [];
            foreach ($processes as $process) {
                preg_match('/^READY (\d+)/m', $process->getOutput(), $matches);
                $ids[] = (int) $matches[1];
            }
            $this->assertCount(2, array_unique($ids));
            $this->assertNotContains((int) $blocker->selectOne('SELECT CONNECTION_ID() AS id')->id, $ids);
            $this->waitFor(fn (): bool => count($connection->select('SELECT trx_mysql_thread_id FROM information_schema.INNODB_TRX WHERE trx_state = ? AND trx_mysql_thread_id IN (?, ?)', ['LOCK WAIT', ...$ids])) === 2,
                'Both workers must actually contend on the wedding row in InnoDB.');
            $blocker->commit();
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $this->assertSame(1, preg_match('/^RESULT (.+)$/m', $process->getOutput(), $match));
                $results[] = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
            }
            $statuses = array_column($results, 'status');
            $fresh = $wedding->fresh();
            if (in_array($schedule, ['publish', 'republish-unpublish'], true)) {
                sort($statuses);
                $this->assertSame(422, $statuses[1]);
                $this->assertContains($statuses[0], $schedule === 'publish' ? [200] : [200, 302]);
            } elseif ($schedule === 'credential-publish') {
                $this->assertSame([200, 200], $statuses);
                $this->assertNotNull($fresh->published_revision_id);
                $this->assertSame(1, $wedding->guests()->whereHas('invitationLink', fn ($query) => $query->whereNotNull('token_hash'))->count());
            } elseif ($schedule === 'media-unpublish') {
                $this->assertContains($statuses[0], [200, 404]);
                $this->assertSame(302, $statuses[1]);
                $this->assertNull($fresh->published_revision_id);
                $this->assertSame(2, $fresh->publication_version);
            } elseif ($schedule === 'rollback-publish') {
                $this->assertSame([500, 200], $statuses);
                $this->assertSame(1, $fresh->publication_version);
                $this->assertSame(1, $fresh->websiteRevisions()->count());
                $this->assertSame(2, $fresh->publishedRevision->mediaAssets()->count());
            } else {
                $this->assertContains($statuses[0], [200, 422]);
                $this->assertSame($schedule === 'save-publish' ? 200 : 302, $statuses[1]);
                if ($fresh->published_revision_id !== null) {
                    $this->assertEquals($source['content'], $fresh->publishedRevision->content);
                    $this->assertSame('Asia/Manila', $fresh->publishedRevision->wedding_facts['timezone']);
                }
                $this->assertSame($schedule === 'save-publish' ? 2 : 1, $fresh->websiteDraft->lock_version);
                $this->assertSame($schedule === 'facts-publish' ? 'Asia/Tokyo' : 'Asia/Manila', $fresh->timezone);
            }
            foreach ($references as $reference) {
                Storage::disk('wedding_media')->assertExists('weddings/'.$wedding->id.'/images/'.$reference['asset_id'].'.webp');
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
            config(['database.default' => $original]);
            DB::purge('cms_publish_mysql');
            DB::purge('cms_publish_blocker');
            $resolved = realpath($root);
            if ($resolved && dirname($resolved) === realpath(sys_get_temp_dir()) && str_starts_with(basename($resolved), 'rsvp-cms-media-')) {
                File::deleteDirectory($resolved);
            }
        }
    }

    private function payload(array $summary): array
    {
        return ['draft_version' => $summary['draftVersion'], 'publication_version' => $summary['publicationVersion'], 'snapshot_token' => $summary['snapshotToken']];
    }

    private function waitFor(callable $condition, string $message): void
    {
        $deadline = microtime(true) + 10;
        do {
            if ($condition()) {
                return;
            }
            usleep(150000);
        } while (microtime(true) < $deadline);

        $this->fail($message);
    }
}
