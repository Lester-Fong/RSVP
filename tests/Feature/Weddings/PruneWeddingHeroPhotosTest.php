<?php

namespace Tests\Feature\Weddings;

use App\Models\Account;
use App\Models\User;
use App\Models\WebsiteDraft;
use App\Services\WeddingHeroPhotoProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PruneWeddingHeroPhotosTest extends TestCase
{
    use RefreshDatabase;

    public function test_pruning_is_dry_run_by_default_and_preserves_referenced_recent_and_unrecognized_files(): void
    {
        $disk = Storage::fake('wedding_photos');
        $account = Account::create(['name' => 'Prune test']);
        $account->users()->attach(User::factory()->create(), ['role' => 'owner']);
        $wedding = $account->weddings()->create(['partner_one_name' => 'A', 'partner_two_name' => 'B', 'public_slug' => fake()->uuid()]);
        $prefix = 'accounts/'.$account->id.'/weddings/'.$wedding->id.'/hero/';
        $referenced = $prefix.Str::uuid().'.webp';
        $orphan = $prefix.Str::uuid().'.webp';
        $recent = $prefix.Str::uuid().'.webp';
        $unknown = $prefix.'not-generated-by-us.webp';
        foreach ([$referenced, $orphan, $recent, $unknown] as $path) {
            $disk->put($path, 'synthetic private bytes');
            if ($path !== $recent) {
                touch($disk->path($path), now()->subDays(2)->timestamp);
            }
        }
        WebsiteDraft::factory()->for($wedding)->create(['hero_photo_disk' => 'wedding_photos', 'hero_photo_path' => $referenced]);

        $this->artisan('wedding-photos:prune')->expectsOutput('Would delete 1 orphaned wedding photo(s).')->assertSuccessful();
        $disk->assertExists([$orphan, $referenced, $recent, $unknown]);
        $this->artisan('wedding-photos:prune --delete')->expectsOutput('Deleted 1 orphaned wedding photo(s).')->assertSuccessful();
        $disk->assertMissing($orphan);
        $disk->assertExists([$referenced, $recent, $unknown]);
    }

    public function test_cleanup_rechecks_committed_references_and_skips_future_schema_weddings(): void
    {
        $disk = Storage::fake('wedding_photos');
        $account = Account::create(['name' => 'Prune future schema']);
        $wedding = $account->weddings()->create(['partner_one_name' => 'A', 'partner_two_name' => 'B', 'public_slug' => fake()->uuid()]);
        $path = 'accounts/'.$account->id.'/weddings/'.$wedding->id.'/hero/'.Str::uuid().'.webp';
        $disk->put($path, 'private');
        touch($disk->path($path), now()->subDays(2)->timestamp);
        $draft = WebsiteDraft::factory()->for($wedding)->create(['hero_photo_disk' => 'wedding_photos', 'hero_photo_path' => $path]);
        $photos = app(WeddingHeroPhotoProcessor::class);

        $this->assertFalse($photos->deleteIfUnreferenced($account->id, $wedding->id, 'wedding_photos', $path));
        $draft->update(['schema_version' => 2, 'hero_photo_disk' => null, 'hero_photo_path' => null]);
        $this->artisan('wedding-photos:prune --delete')->expectsOutput('Deleted 0 orphaned wedding photo(s).')->assertSuccessful();
        $disk->assertExists($path);
    }

    public function test_pruning_removes_aged_objects_after_the_wedding_was_deleted(): void
    {
        $disk = Storage::fake('wedding_photos');
        $account = Account::create(['name' => 'Deleted wedding']);
        $wedding = $account->weddings()->create(['partner_one_name' => 'A', 'partner_two_name' => 'B', 'public_slug' => fake()->uuid()]);
        $path = 'accounts/'.$account->id.'/weddings/'.$wedding->id.'/hero/'.Str::uuid().'.webp';
        $disk->put($path, 'private');
        touch($disk->path($path), now()->subDays(2)->timestamp);
        $wedding->delete();

        $this->artisan('wedding-photos:prune --delete')->expectsOutput('Deleted 1 orphaned wedding photo(s).')->assertSuccessful();
        $disk->assertMissing($path);
    }
}
