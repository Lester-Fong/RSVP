<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\WebsiteDraft;
use App\Models\WebsiteRevision;
use App\Models\WeddingMediaAsset;
use App\Services\CreateWebsiteRevision;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\TestDatabaseGuard;
use Tests\TestCase;

#[Group('mysql-schema')]
class CmsPersistenceMySqlTest extends TestCase
{
    public function test_prepared_innodb_enforces_cms_metadata_media_uniqueness_and_restrictive_foreign_keys(): void
    {
        $configuration = TestDatabaseGuard::disposableMySqlConfigurationFromProcess();
        if ($configuration === null) {
            $this->markTestSkipped('Set the explicit CMS_MYSQL_TEST_* disposable InnoDB environment; no schema is created or reset.');
        }
        $database = $configuration['database'];
        $this->assertTrue($this->app->environment('testing'));
        $this->assertMatchesRegularExpression('/\Awedding_rsvp_cms_test(?:_[a-z0-9_]+)?\z/D', $database);
        $this->assertContains($configuration['host'], ['127.0.0.1', 'localhost', '::1'], 'Only a verified local disposable target is permitted.');
        config(['database.connections.cms_persistence_mysql_test' => $configuration]);
        $original = config('database.default');

        try {
            $connection = DB::connection('cms_persistence_mysql_test');
            try {
                $this->assertSame($database, $connection->selectOne('SELECT DATABASE() AS name')->name);
                foreach (['website_drafts', 'website_revisions'] as $table) {
                    if (! $connection->getSchemaBuilder()->hasColumns($table, ['template_key', 'template_version'])) {
                        $this->markTestSkipped('The disposable target needs the CMS-A.2 migrations; this test never applies them.');
                    }
                }
                foreach (['wedding_media_assets', 'website_revision_media'] as $table) {
                    if (! $connection->getSchemaBuilder()->hasTable($table)) {
                        $this->markTestSkipped('The disposable target needs both CMS-A.2 media tables; no schema is changed.');
                    }
                }
                $engines = $connection->select('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (?, ?, ?, ?)', [
                    $database, 'website_drafts', 'website_revisions', 'wedding_media_assets', 'website_revision_media',
                ]);
                $this->assertCount(4, $engines);
                foreach ($engines as $table) {
                    $this->assertSame('InnoDB', $table->ENGINE);
                }
                $mode = $connection->selectOne('SELECT @@SESSION.sql_mode AS modes')->modes;
                $this->assertTrue(str_contains($mode, 'STRICT_TRANS_TABLES') || str_contains($mode, 'STRICT_ALL_TABLES'));
            } catch (QueryException|PDOException) {
                $this->markTestSkipped('Prepared isolated MySQL is unavailable; development data is never a fallback.');
            }
            config(['database.default' => 'cms_persistence_mysql_test']);
            $connection->beginTransaction();
            try {
                $wedding = Account::create(['name' => 'Synthetic CMS InnoDB fixture'])->weddings()->create([
                    'partner_one_name' => 'Synthetic Alex', 'partner_two_name' => 'Synthetic Jamie', 'public_slug' => (string) Str::uuid(),
                ]);
                $draft = WebsiteDraft::factory()->for($wedding)->create()->fresh();
                $asset = WeddingMediaAsset::factory()->for($wedding)->create();
                $revision = app(CreateWebsiteRevision::class)->create($wedding, WebsiteRevision::factory()->for($wedding)->make(), [$asset->id]);

                $this->assertSame('modern-classic', $draft->template_key);
                $this->assertSame(1, $draft->template_version);
                foreach (['website_drafts', 'website_revisions'] as $table) {
                    foreach ([-1, 65536] as $invalid) {
                        $this->assertRejected(fn () => $connection->table($table)->where('wedding_id', $wedding->id)->update(['template_version' => $invalid]));
                    }
                    foreach (['template_key', 'template_version'] as $column) {
                        $this->assertRejected(fn () => $connection->table($table)->where('wedding_id', $wedding->id)->update([$column => null]));
                    }
                }
                $this->assertRejected(fn () => $connection->table('website_revision_media')->insert(['website_revision_id' => $revision->id, 'wedding_media_asset_id' => $asset->id]));
                $this->assertRejected(fn () => $connection->table('website_revision_media')->insert(['website_revision_id' => 0, 'wedding_media_asset_id' => $asset->id]));
                $this->assertRejected(fn () => $connection->table('website_revision_media')->insert(['website_revision_id' => $revision->id, 'wedding_media_asset_id' => (string) Str::uuid()]));
                $this->assertRejected(fn () => $connection->table('wedding_media_assets')->insert(array_replace($asset->getAttributes(), ['id' => (string) Str::uuid()])));
                $this->assertRejected(fn () => $connection->table('wedding_media_assets')->insert(array_replace($asset->getAttributes(), ['id' => (string) Str::uuid(), 'wedding_id' => 0, 'path' => 'synthetic-other.webp'])));
                $this->assertRejected(fn () => $connection->table('wedding_media_assets')->where('id', $asset->id)->update(['kind' => 'video']));
                foreach (['byte_size', 'width', 'height'] as $column) {
                    $this->assertRejected(fn () => $connection->table('wedding_media_assets')->where('id', $asset->id)->update([$column => -1]));
                }
                foreach (['wedding_media_assets' => $asset->id, 'website_revisions' => $revision->id, 'weddings' => $wedding->id] as $table => $id) {
                    $this->assertRejected(fn () => $connection->table($table)->where('id', $id)->delete());
                }
                $this->assertSame(1, $draft->fresh()->template_version);
                $this->assertSame(1, $revision->fresh()->template_version);
                $this->assertSame(1, $revision->mediaAssets()->count());
            } finally {
                $connection->rollBack();
            }
        } finally {
            config(['database.default' => $original]);
            DB::purge('cms_persistence_mysql_test');
        }
    }

    private function assertRejected(Closure $operation): void
    {
        try {
            $operation();
        } catch (QueryException $exception) {
            $this->assertNotEmpty($exception->errorInfo);

            return;
        }
        $this->fail('MySQL accepted a constrained write or deletion.');
    }
}
