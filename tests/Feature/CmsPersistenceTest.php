<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\WebsiteDraft;
use App\Models\WebsiteRevision;
use App\Models\Wedding;
use App\Services\CreateWebsiteRevision;
use App\Services\WeddingPublication;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CmsPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_additive_metadata_upgrade_preserves_existing_drafts_snapshots_and_publication(): void
    {
        $migration = $this->metadataMigration();
        $migration->down();
        $wedding = $this->wedding();
        WebsiteDraft::factory()->for($wedding)->create(['lock_version' => 7]);
        $revision = WebsiteRevision::factory()->for($wedding)->create();
        $wedding->forceFill(['published_revision_id' => $revision->id, 'publication_version' => 4])->save();
        $draftBefore = (array) DB::table('website_drafts')->sole();
        $revisionBefore = (array) DB::table('website_revisions')->sole();
        $weddingBefore = $wedding->fresh()->getAttributes();

        $migration->up();

        $this->assertSame($draftBefore, (array) DB::table('website_drafts')->select(array_keys($draftBefore))->sole());
        $this->assertSame($revisionBefore, (array) DB::table('website_revisions')->select(array_keys($revisionBefore))->sole());
        $this->assertSame($weddingBefore, $wedding->fresh()->getAttributes());
        foreach ([$wedding->websiteDraft()->sole(), $revision->fresh()] as $document) {
            $this->assertSame('modern-classic', $document->template_key);
            $this->assertSame(1, $document->template_version);
            $this->assertSame(1, $document->schema_version);
        }
    }

    #[DataProvider('requiredMetadata')]
    public function test_template_metadata_cannot_be_null(string $table, string $column): void
    {
        $wedding = $this->wedding();
        WebsiteDraft::factory()->for($wedding)->create();
        WebsiteRevision::factory()->for($wedding)->create();

        $this->expectException(QueryException::class);
        DB::table($table)->update([$column => null]);
    }

    public static function requiredMetadata(): array
    {
        return [['website_drafts', 'template_key'], ['website_drafts', 'template_version'],
            ['website_revisions', 'template_key'], ['website_revisions', 'template_version']];
    }

    public function test_unmigrated_legacy_schema_can_still_save_and_publish_without_new_column_queries(): void
    {
        $migration = $this->metadataMigration();
        $mediaMigration = require database_path('migrations/2026_10_20_112443_create_wedding_media_assets_table.php');
        $referencesMigration = require database_path('migrations/2026_10_20_112444_create_website_revision_media_table.php');
        $referencesMigration->down();
        $mediaMigration->down();
        $migration->down();
        try {
            $this->withoutVite();
            $wedding = $this->wedding();
            WebsiteDraft::factory()->for($wedding)->create();
            $publication = app(WeddingPublication::class);
            $summary = $publication->summary($wedding->fresh());

            $publication->publish($wedding, ['draft_version' => $summary['draftVersion'],
                'publication_version' => $summary['publicationVersion'], 'snapshot_token' => $summary['snapshotToken']]);

            $this->assertSame(1, $wedding->websiteRevisions()->count());
            $this->get(route('public.weddings.show', $wedding->public_slug))->assertOk()->assertSee('Alex');
            $this->assertFalse(Schema::hasTable('wedding_media_assets'));
        } finally {
            $migration->up();
            $mediaMigration->up();
            $referencesMigration->up();
        }
    }

    public function test_independent_intro_settings_round_trip_without_activating_v2(): void
    {
        $wedding = $this->wedding();
        $fixtures = json_decode(file_get_contents(__DIR__.'/../Fixtures/cms-v2-contracts.json'), true, 32, JSON_THROW_ON_ERROR);
        $document = $fixtures['base'];
        $draft = WebsiteDraft::factory()->for($wedding)->create([
            'schema_version' => 2, 'template_key' => $document['template_key'],
            'template_version' => $document['template_version'], 'content' => $document['content'],
        ]);
        $input = WebsiteRevision::factory()->for($wedding)->make([
            'schema_version' => 2, 'template_key' => $document['template_key'],
            'template_version' => $document['template_version'], 'content' => $document['content'],
        ]);

        $revision = app(CreateWebsiteRevision::class)->create($wedding, $input);

        $this->assertSame($document['content']['experience'], $draft->fresh()->content['experience']);
        $this->assertSame($document['content'], $revision->fresh()->content);
        $this->assertSame('floral-trial', $revision->template_key);
        $this->assertSame(1, $revision->template_version);
        $this->assertNull($wedding->fresh()->published_revision_id);
        $this->assertSame(0, $wedding->fresh()->publication_version);
        $this->get(route('public.weddings.show', $wedding->public_slug))->assertNotFound();
        $this->assertFalse($input->exists);
    }

    #[DataProvider('unsupportedMetadata')]
    public function test_snapshot_boundary_rejects_unsupported_template_metadata(string $key, int $version, int $schema): void
    {
        $wedding = $this->wedding();
        $input = WebsiteRevision::factory()->for($wedding)->make(['template_key' => $key, 'template_version' => $version, 'schema_version' => $schema]);

        try {
            app(CreateWebsiteRevision::class)->create($wedding, $input);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('template', $exception->errors());
            $this->assertDatabaseCount('website_revisions', 0);

            return;
        }
        $this->fail('Unsupported template metadata was persisted.');
    }

    public static function unsupportedMetadata(): array
    {
        return [['modern-classic', 0, 1], ['modern-classic', -1, 1], ['modern-classic', 2, 1],
            ['unknown', 1, 1], ['modern-classic', 1, 2], ['floral-trial', 1, 1]];
    }

    public function test_mysql_ddl_is_additive_with_expected_types_indexes_and_restrictions_without_connecting(): void
    {
        $connection = new MySqlConnection(fn () => throw new RuntimeException('Offline DDL must not connect.'), 'synthetic_ddl', '', [
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'engine' => 'InnoDB',
        ]);
        $original = Schema::getFacadeRoot();
        try {
            Schema::swap($connection->getSchemaBuilder());
            $queries = $connection->pretend(function (): void {
                $this->metadataMigration()->up();
                (require database_path('migrations/2026_10_20_112443_create_wedding_media_assets_table.php'))->up();
                (require database_path('migrations/2026_10_20_112444_create_website_revision_media_table.php'))->up();
            });
        } finally {
            Schema::swap($original);
        }
        $ddl = implode(";\n", array_column($queries, 'query'));

        $this->assertSame(2, substr_count($ddl, "`template_key` varchar(64) not null default 'modern-classic'"));
        $this->assertSame(2, substr_count($ddl, "`template_version` smallint unsigned not null default '1'"));
        $this->assertStringContainsString('`id` char(36) not null', $ddl);
        $this->assertStringContainsString("`kind` enum('image') not null", $ddl);
        $this->assertStringContainsString('`byte_size` int unsigned not null', $ddl);
        $this->assertStringContainsString('primary key (`website_revision_id`, `wedding_media_asset_id`)', $ddl);
        $this->assertSame(3, substr_count($ddl, 'on delete restrict'));
        $this->assertStringNotContainsString('drop ', $ddl);
        $this->assertStringNotContainsString('timezone', $ddl);
    }

    private function metadataMigration(): object
    {
        return require database_path('migrations/2026_10_20_112443_add_template_metadata_to_website_documents.php');
    }

    private function wedding(): Wedding
    {
        return Account::create(['name' => 'Synthetic CMS workspace'])->weddings()->create([
            'partner_one_name' => 'Alex', 'partner_two_name' => 'Jamie', 'wedding_date' => '2027-05-12', 'public_slug' => fake()->uuid(),
        ]);
    }
}
