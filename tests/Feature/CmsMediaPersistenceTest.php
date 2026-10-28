<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\WebsiteRevision;
use App\Models\WebsiteRevisionMedia;
use App\Models\Wedding;
use App\Models\WeddingMediaAsset;
use App\Services\CreateWebsiteRevision;
use App\Services\WeddingImageAssetStorage;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CmsMediaPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_storage_is_private_and_does_not_create_a_public_delivery_surface(): void
    {
        $asset = WeddingMediaAsset::factory()->for($this->wedding())->create();
        $disk = Storage::disk(WeddingImageAssetStorage::DISK);

        $disk->put($asset->path, 'synthetic-storage-fixture-not-an-image');

        $disk->assertExists($asset->path);
        $this->assertSame('private', config('filesystems.disks.wedding_media.visibility'));
        $this->assertFalse(config('filesystems.disks.wedding_media.serve'));
        $this->assertStringStartsWith(storage_path('app/private/wedding-media'), $disk->path($asset->path));
        $this->get('/storage/wedding-media/'.$asset->path)->assertForbidden();
        $this->assertArrayNotHasKey('url', $asset->toArray());
    }

    public function test_owned_immutable_images_and_revision_relationships_hide_private_storage(): void
    {
        $wedding = $this->wedding();
        $asset = WeddingMediaAsset::factory()->for($wedding)->create();
        $revision = app(CreateWebsiteRevision::class)->create($wedding, WebsiteRevision::factory()->for($wedding)->make(), [$asset->id]);

        $this->assertTrue(Str::isUuid($asset->id));
        $this->assertTrue($asset->wedding->is($wedding));
        $this->assertTrue($wedding->mediaAssets()->sole()->is($asset));
        $this->assertTrue($asset->revisions()->sole()->is($revision));
        $visible = $revision->mediaAssets()->sole()->toArray();
        $this->assertSame(['id', 'kind', 'mime_type', 'byte_size', 'width', 'height', 'created_at', 'updated_at'], array_keys($visible));
        $this->assertSame('image/webp', $visible['mime_type']);
        $this->assertSame(1600, $visible['width']);
        $this->assertSame(120000, $visible['byte_size']);
        $this->assertSame([], WebsiteRevisionMedia::query()->sole()->toArray());
        $this->assertNull($wedding->fresh()->published_revision_id);
    }

    public function test_customer_mass_assignment_cannot_choose_storage_or_ownership(): void
    {
        $this->expectException(MassAssignmentException::class);
        WeddingMediaAsset::create(['wedding_id' => 1, 'disk' => 'public', 'path' => 'arbitrary']);
    }

    #[DataProvider('invalidImageMetadata')]
    public function test_only_code_owned_processed_image_metadata_is_accepted(array $invalid): void
    {
        $wedding = $this->wedding();

        try {
            WeddingMediaAsset::factory()->for($wedding)->create($invalid);
        } catch (ValidationException) {
            $this->assertDatabaseCount('wedding_media_assets', 0);

            return;
        }
        $this->fail('Unsafe image metadata was accepted.');
    }

    public static function invalidImageMetadata(): array
    {
        return ['public disk' => [['disk' => 'public']], 'arbitrary path' => [['path' => 'https://example.test/image.webp']],
            'foreign path' => [['path' => 'weddings/999/images/foreign.webp']], 'unsupported kind' => [['kind' => 'video']],
            'unsafe MIME' => [['mime_type' => 'image/svg+xml']], 'empty bytes' => [['byte_size' => 0]],
            'oversized bytes' => [['byte_size' => 5242881]], 'invalid width' => [['width' => 0]],
            'oversized height' => [['height' => 1601]], 'invalid UUID' => [['id' => 'sequential-guest-id']]];
    }

    #[DataProvider('invalidReferences')]
    public function test_invalid_media_batches_fail_before_persisting_a_snapshot(string $case): void
    {
        $wedding = $this->wedding();
        $own = WeddingMediaAsset::factory()->for($wedding)->create();
        $foreign = WeddingMediaAsset::factory()->for($this->wedding())->create();
        $ids = match ($case) {
            'foreign' => [$own->id, $foreign->id],
            'missing' => [$own->id, (string) Str::uuid()],
            'duplicate' => [$own->id, $own->id],
            'invalid' => ['not-an-asset'],
            'nested' => [[$own->id]],
        };

        try {
            app(CreateWebsiteRevision::class)->create($wedding, WebsiteRevision::factory()->for($wedding)->make(), $ids);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('media', $exception->errors());
            $this->assertDatabaseCount('website_revisions', 0);
            $this->assertDatabaseCount('website_revision_media', 0);
            $this->assertDatabaseCount('wedding_media_assets', 2);

            return;
        }
        $this->fail('An invalid media batch was persisted.');
    }

    public static function invalidReferences(): array
    {
        return [['foreign'], ['missing'], ['duplicate'], ['invalid'], ['nested']];
    }

    public function test_same_account_does_not_allow_cross_wedding_media(): void
    {
        $wedding = $this->wedding();
        $other = $wedding->account->weddings()->create(['partner_one_name' => 'Other', 'partner_two_name' => 'Couple', 'public_slug' => fake()->uuid()]);
        $asset = WeddingMediaAsset::factory()->for($other)->create();

        $this->expectException(ValidationException::class);
        app(CreateWebsiteRevision::class)->create($wedding, WebsiteRevision::factory()->for($wedding)->make(), [$asset->id]);
    }

    public function test_snapshot_ownership_cannot_be_reassigned_to_the_supplied_parent(): void
    {
        $wedding = $this->wedding();
        $other = $this->wedding();

        $this->expectException(ValidationException::class);
        app(CreateWebsiteRevision::class)->create($wedding, WebsiteRevision::factory()->for($other)->make());
    }

    public function test_reference_model_rejects_cross_wedding_foreign_keys_even_if_both_parents_exist(): void
    {
        $wedding = $this->wedding();
        $revision = WebsiteRevision::factory()->for($wedding)->create();
        $asset = WeddingMediaAsset::factory()->for($this->wedding())->create();
        $reference = new WebsiteRevisionMedia;
        $reference->forceFill(['website_revision_id' => $revision->id, 'wedding_media_asset_id' => $asset->id]);
        $reference->setRelation('revision', $revision);

        $this->expectException(ValidationException::class);
        $reference->save();
    }

    public function test_failure_after_reference_insertion_rolls_back_the_complete_snapshot(): void
    {
        $wedding = $this->wedding();
        $assets = WeddingMediaAsset::factory()->for($wedding)->count(2)->create();
        $input = WebsiteRevision::factory()->for($wedding)->make();
        WebsiteRevisionMedia::created(function (): void {
            throw new RuntimeException('Synthetic reference-write failure.');
        });

        try {
            app(CreateWebsiteRevision::class)->create($wedding, $input, $assets->modelKeys());
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic reference-write failure.', $exception->getMessage());
            $this->assertDatabaseCount('website_revisions', 0);
            $this->assertDatabaseCount('website_revision_media', 0);
            $this->assertDatabaseCount('wedding_media_assets', 2);
            $this->assertFalse($input->exists);
            $this->assertNull($wedding->fresh()->published_revision_id);

            return;
        }
        $this->fail('The synthetic write failure did not occur.');
    }

    #[DataProvider('immutableOperations')]
    public function test_ordinary_model_writes_cannot_mutate_snapshot_media(string $operation): void
    {
        $wedding = $this->wedding();
        $asset = WeddingMediaAsset::factory()->for($wedding)->create();
        $replacement = WeddingMediaAsset::factory()->for($wedding)->create();
        $service = app(CreateWebsiteRevision::class);
        $revision = $service->create($wedding, WebsiteRevision::factory()->for($wedding)->make(), [$asset->id]);
        $reference = $revision->mediaAssets()->sole()->pivot;

        try {
            match ($operation) {
                'asset update' => $asset->forceFill(['width' => 20])->save(),
                'reference update' => $reference->forceFill(['wedding_media_asset_id' => $replacement->id])->save(),
                'detach' => $revision->mediaAssets()->detach($asset->id),
                'append' => $revision->mediaAssets()->attach($replacement->id),
                'saved snapshot' => $service->create($wedding, $revision, [$replacement->id]),
                'pinned delete' => $asset->delete(),
                'snapshot metadata' => $revision->forceFill(['template_version' => 2])->save(),
            };
        } catch (LogicException) {
            $this->assertSame(1600, $asset->fresh()->width);
            $this->assertSame(1, $revision->fresh()->template_version);
            $this->assertSame([$asset->id], $revision->mediaAssets()->pluck('wedding_media_assets.id')->all());
            $this->assertDatabaseCount('website_revision_media', 1);

            return;
        }
        $this->fail('Immutable snapshot data was changed: '.$operation);
    }

    public static function immutableOperations(): array
    {
        $cases = [];
        foreach (['asset update', 'reference update', 'detach', 'append', 'saved snapshot', 'pinned delete', 'snapshot metadata'] as $operation) {
            $cases[$operation] = [$operation];
        }

        return $cases;
    }

    #[DataProvider('restrictedDeletes')]
    public function test_foreign_keys_restrict_raw_sql_deletion_of_owned_or_referenced_records(string $table): void
    {
        $wedding = $this->wedding();
        $asset = WeddingMediaAsset::factory()->for($wedding)->create();
        $revision = app(CreateWebsiteRevision::class)->create($wedding, WebsiteRevision::factory()->for($wedding)->make(), [$asset->id]);
        $id = match ($table) {
            'weddings' => $wedding->id, 'website_revisions' => $revision->id, 'wedding_media_assets' => $asset->id
        };

        $this->expectException(QueryException::class);
        DB::table($table)->where('id', $id)->delete();
    }

    public static function restrictedDeletes(): array
    {
        return [['weddings'], ['website_revisions'], ['wedding_media_assets']];
    }

    #[DataProvider('databaseViolations')]
    public function test_database_rejects_missing_parents_duplicate_references_and_storage_aliases(string $violation): void
    {
        $wedding = $this->wedding();
        $asset = WeddingMediaAsset::factory()->for($wedding)->create();
        $revision = app(CreateWebsiteRevision::class)->create($wedding, WebsiteRevision::factory()->for($wedding)->make(), [$asset->id]);

        $this->expectException(QueryException::class);
        match ($violation) {
            'duplicate reference' => DB::table('website_revision_media')->insert(['website_revision_id' => $revision->id, 'wedding_media_asset_id' => $asset->id]),
            'missing revision' => DB::table('website_revision_media')->insert(['website_revision_id' => 0, 'wedding_media_asset_id' => $asset->id]),
            'missing asset' => DB::table('website_revision_media')->insert(['website_revision_id' => $revision->id, 'wedding_media_asset_id' => (string) Str::uuid()]),
            'missing wedding' => DB::table('wedding_media_assets')->insert(array_replace($asset->getAttributes(), ['id' => (string) Str::uuid(), 'wedding_id' => 0, 'path' => 'other.webp'])),
            'storage alias' => DB::table('wedding_media_assets')->insert(array_replace($asset->getAttributes(), ['id' => (string) Str::uuid()])),
            'unsupported kind' => DB::table('wedding_media_assets')->where('id', $asset->id)->update(['kind' => 'video']),
        };
    }

    public static function databaseViolations(): array
    {
        return [['duplicate reference'], ['missing revision'], ['missing asset'], ['missing wedding'], ['storage alias'], ['unsupported kind']];
    }

    public function test_unreferenced_asset_row_can_be_removed_without_implementing_file_cleanup(): void
    {
        $asset = WeddingMediaAsset::factory()->for($this->wedding())->create();

        $asset->delete();

        $this->assertDatabaseCount('wedding_media_assets', 0);
    }

    private function wedding(): Wedding
    {
        return Account::create(['name' => 'Synthetic media workspace'])->weddings()->create([
            'partner_one_name' => 'Alex', 'partner_two_name' => 'Jamie', 'wedding_date' => '2027-05-12', 'public_slug' => fake()->uuid(),
        ]);
    }
}
