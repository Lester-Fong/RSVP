<?php

namespace Tests\Feature\Weddings;

use App\Models\Account;
use App\Models\User;
use App\Models\WebsiteDraft;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class WeddingHeroPhotoTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('formats')]
    public function test_owner_saves_normalized_private_photo_and_both_previews_load_it(string $extension): void
    {
        $disk = Storage::fake('wedding_photos');
        [$owner, $wedding] = $this->ownedWedding();
        $facts = $wedding->fresh()->getAttributes();

        $this->actingAs($owner)->post($this->saveUrl($wedding), $this->payload(UploadedFile::fake()->image('photo.'.$extension, 2400, 1200)), ['Content-Type' => 'multipart/form-data'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $draft = $wedding->websiteDraft()->sole();
        $this->assertSame(1, $draft->schema_version);
        $this->assertSame(1, $draft->lock_version);
        $this->assertSame(WebsiteDraft::defaultContent(), $draft->content);
        $this->assertSame($facts, $wedding->fresh()->getAttributes());
        $disk->assertCount('accounts', 1, true);
        $image = getimagesizefromstring($disk->get($draft->hero_photo_path));
        $this->assertSame([1600, 800, IMAGETYPE_WEBP], array_slice($image, 0, 3));
        $url = $draft->heroPhoto()['url'];
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'no-store, private')->assertStreamedContent($disk->get($draft->hero_photo_path));

        foreach (['accounts.weddings.invitation.edit', 'accounts.weddings.preview'] as $route) {
            $this->get(route($route, [$wedding->account_id, $wedding]))->assertInertia(fn (Assert $page) => $page->where('heroPhoto', ['url' => $url]));
        }
        $this->assertArrayNotHasKey('hero_photo_path', $draft->toArray());
        $this->assertArrayNotHasKey('hero_photo_disk', $draft->toArray());
    }

    public static function formats(): array
    {
        return [['jpeg'], ['png'], ['webp']];
    }

    #[DataProvider('uploadErrors')]
    public function test_failed_upload_returns_actionable_error_and_preserves_saved_photo_and_content(int $error, string $message): void
    {
        $disk = Storage::fake('wedding_photos');
        [$owner, $wedding] = $this->ownedWedding();
        $this->actingAs($owner)->post($this->saveUrl($wedding), $this->payload(UploadedFile::fake()->image('saved.png')), ['Content-Type' => 'multipart/form-data'])
            ->assertSessionHasNoErrors();
        $draft = $wedding->websiteDraft()->sole();
        $before = $draft->getAttributes();
        $bytes = $disk->get($draft->hero_photo_path);
        $file = new UploadedFile('', 'interrupted.png', 'image/png', $error, true);
        $payload = $this->payload($file, 1);
        $content = WebsiteDraft::defaultContent();
        $content['introduction_message'] = 'This must not be saved after upload failure.';
        $content['sections']['reception'] = false;
        $payload['content'] = json_encode($content);

        $this->post($this->saveUrl($wedding), $payload, ['Content-Type' => 'multipart/form-data', 'Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors(['hero_photo'])
            ->assertJsonPath('errors.hero_photo.0', $message);

        $this->assertSame($before, $draft->fresh()->getAttributes());
        $this->assertSame($bytes, $disk->get($draft->hero_photo_path));
        $disk->assertCount('accounts', 1, true);
        $this->get(route('accounts.weddings.invitation.edit', [$wedding->account_id, $wedding]))
            ->assertInertia(fn (Assert $page) => $page->where('customization', $draft->content)->where('draftVersion', 1)->where('heroPhoto', $draft->heroPhoto()));
    }

    public static function uploadErrors(): array
    {
        $server = 'The server could not accept your photo. Please contact support. Your saved draft has not changed.';

        return [
            [UPLOAD_ERR_INI_SIZE, 'This photo exceeds the server upload limit. Choose a photo up to 5 MiB; if it already meets that limit, please contact support. Your saved draft has not changed.'],
            [UPLOAD_ERR_FORM_SIZE, 'Choose a JPEG, PNG, or WebP photo up to 5 MiB. Your saved draft has not changed.'],
            [UPLOAD_ERR_PARTIAL, 'Your photo did not finish uploading. Select it again and retry. Your saved draft has not changed.'],
            [UPLOAD_ERR_NO_TMP_DIR, $server],
            [UPLOAD_ERR_CANT_WRITE, $server],
            [UPLOAD_ERR_EXTENSION, $server],
        ];
    }

    public function test_replace_keep_remove_and_superseded_urls_preserve_wedding_data(): void
    {
        $disk = Storage::fake('wedding_photos');
        [$owner, $wedding] = $this->ownedWedding();
        $this->actingAs($owner)->put($this->saveUrl($wedding), $this->payload(UploadedFile::fake()->image('first.png'), 0, false))->assertSessionHasNoErrors();
        $first = $wedding->websiteDraft()->sole();
        $oldPath = $first->hero_photo_path;
        $oldUrl = $first->heroPhoto()['url'];

        $this->put($this->saveUrl($wedding), $this->payload(UploadedFile::fake()->image('second.png'), 1, false))->assertSessionHasNoErrors();
        $replacement = $first->fresh();
        $disk->assertMissing($oldPath);
        $disk->assertExists($replacement->hero_photo_path);
        $this->get($oldUrl)->assertNotFound();
        $this->put($this->saveUrl($wedding), ['content' => WebsiteDraft::defaultContent(), 'draft_version' => 2])->assertSessionHasNoErrors();
        $this->assertSame($replacement->hero_photo_path, $first->fresh()->hero_photo_path);

        $this->put($this->saveUrl($wedding), ['content' => WebsiteDraft::defaultContent(), 'draft_version' => 3, 'hero_photo_action' => 'remove'])->assertSessionHasNoErrors();
        $this->assertNull($first->fresh()->hero_photo_path);
        $this->assertNull($first->fresh()->hero_photo_disk);
        $this->assertSame(4, $first->fresh()->lock_version);
        $disk->assertDirectoryEmpty('accounts');
        $this->get($replacement->heroPhoto()['url'])->assertNotFound();
    }

    public function test_photo_routes_and_upload_enforce_authentication_ownership_and_nested_scoping(): void
    {
        $disk = Storage::fake('wedding_photos');
        [$owner, $wedding] = $this->ownedWedding();
        [$stranger, $other] = $this->ownedWedding();
        $url = route('accounts.weddings.invitation.hero-photo', [$wedding->account_id, $wedding, fake()->uuid()]);
        $this->get($url)->assertRedirect(route('login'));
        $this->put($this->saveUrl($wedding), $this->payload(UploadedFile::fake()->image('x.png'), 0, false))->assertRedirect(route('login'));
        $this->actingAs($stranger)->get($url)->assertForbidden();
        $this->put($this->saveUrl($wedding), $this->payload(UploadedFile::fake()->image('x.png'), 0, false))->assertForbidden();
        $this->actingAs($owner)->get(route('accounts.weddings.invitation.hero-photo', [$other->account_id, $wedding, fake()->uuid()]))->assertNotFound();
        $this->put(route('accounts.weddings.invitation.update', [$other->account_id, $wedding]), $this->payload(UploadedFile::fake()->image('x.png'), 0, false))->assertNotFound();
        $wedding->account->users()->attach($stranger, ['role' => 'viewer']);
        $this->actingAs($stranger)->get($url)->assertForbidden();
        $this->put($this->saveUrl($wedding), $this->payload(UploadedFile::fake()->image('x.png'), 0, false))->assertForbidden();
        $this->assertDatabaseCount('website_drafts', 0);
        $disk->assertDirectoryEmpty('accounts');
    }

    public function test_stale_replacement_and_removal_cannot_change_saved_reference_or_files(): void
    {
        $disk = Storage::fake('wedding_photos');
        [$owner, $wedding] = $this->ownedWedding();
        $this->actingAs($owner)->put($this->saveUrl($wedding), $this->payload(UploadedFile::fake()->image('x.png'), 0, false))->assertSessionHasNoErrors();
        $draft = $wedding->websiteDraft()->sole();
        $before = $draft->getAttributes();

        $this->put($this->saveUrl($wedding), $this->payload(UploadedFile::fake()->image('loser.png'), 0, false))->assertSessionHasErrors('draft_version');
        $this->put($this->saveUrl($wedding), ['content' => WebsiteDraft::defaultContent(), 'draft_version' => 0, 'hero_photo_action' => 'remove'])->assertSessionHasErrors('draft_version');

        $this->assertSame($before, $draft->fresh()->getAttributes());
        $disk->assertCount('accounts', 1, true);
        $disk->assertExists($draft->hero_photo_path);
    }

    public function test_unsupported_schema_blocks_photo_reads_and_writes_without_side_effects(): void
    {
        $disk = Storage::fake('wedding_photos');
        [$owner, $wedding] = $this->ownedWedding();
        $draft = WebsiteDraft::factory()->for($wedding)->create(['schema_version' => 2]);
        $before = $draft->fresh()->getAttributes();
        $this->actingAs($owner)->putJson($this->saveUrl($wedding), ['content' => WebsiteDraft::defaultContent(), 'draft_version' => 1, 'hero_photo_action' => 'remove'])->assertConflict();
        $this->put($this->saveUrl($wedding), $this->payload(UploadedFile::fake()->image('future.png'), 1, false))->assertConflict();
        $this->getJson(route('accounts.weddings.invitation.hero-photo', [$wedding->account_id, $wedding, fake()->uuid()]))->assertConflict();
        $this->assertSame($before, $draft->fresh()->getAttributes());
        $disk->assertDirectoryEmpty('accounts');
    }

    #[DataProvider('invalidPhotos')]
    public function test_invalid_upload_is_rejected_without_persisting_a_draft(string $kind): void
    {
        $disk = Storage::fake('wedding_photos');
        [$owner, $wedding] = $this->ownedWedding();
        $file = match ($kind) {
            'html' => UploadedFile::fake()->createWithContent('fake.jpg', '<script>alert(1)</script>'),
            'svg' => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
            'gif' => UploadedFile::fake()->image('x.gif'),
            'heic' => UploadedFile::fake()->createWithContent('x.heic', 'unsupported'),
            'oversize' => UploadedFile::fake()->image('x.png')->size(5121),
            'wide' => UploadedFile::fake()->image('x.png', 6001, 1),
            'pixels' => UploadedFile::fake()->image('x.png', 4001, 3000),
        };
        $this->actingAs($owner)->put($this->saveUrl($wedding), $this->payload($file, 0, false))->assertSessionHasErrors('hero_photo');
        $this->assertDatabaseCount('website_drafts', 0);
        $disk->assertDirectoryEmpty('accounts');
    }

    public static function invalidPhotos(): array
    {
        return array_map(fn (string $kind): array => [$kind], ['html', 'svg', 'gif', 'heic', 'oversize', 'wide', 'pixels']);
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_transport_and_server_controlled_fields_are_rejected(array $changes, string $error): void
    {
        $disk = Storage::fake('wedding_photos');
        [$owner, $wedding] = $this->ownedWedding();
        $payload = array_replace(['_method' => 'put', 'content' => json_encode(WebsiteDraft::defaultContent()), 'draft_version' => '0'], $changes);
        $this->actingAs($owner)->post($this->saveUrl($wedding), $payload, ['Content-Type' => 'multipart/form-data'])->assertSessionHasErrors($error);
        $this->assertDatabaseCount('website_drafts', 0);
        $disk->assertDirectoryEmpty('accounts');
    }

    public static function invalidPayloads(): array
    {
        $content = WebsiteDraft::defaultContent();
        $content['sections']['closing'] = '1';

        return [
            [['content' => '{'], 'content'],
            [['content' => json_encode($content)], 'content.sections.closing'],
            [['content' => str_repeat('x', 16385)], 'content'],
            [['hero_photo_action' => 'replace'], 'hero_photo'],
            [['hero_photo_action' => 'publish'], 'hero_photo_action'],
            [['hero_photo_path' => 'accounts/other.webp'], 'hero_photo_path'],
            [['hero_photo_disk' => 'public'], 'hero_photo_disk'],
        ];
    }

    public function test_keep_and_remove_reject_an_attached_file(): void
    {
        $disk = Storage::fake('wedding_photos');
        [$owner, $wedding] = $this->ownedWedding();
        foreach (['keep', 'remove'] as $action) {
            $payload = $this->payload(UploadedFile::fake()->image('x.png'), 0, false);
            $payload['hero_photo_action'] = $action;
            $this->actingAs($owner)->put($this->saveUrl($wedding), $payload)->assertSessionHasErrors('hero_photo');
        }
        $this->assertDatabaseCount('website_drafts', 0);
        $disk->assertDirectoryEmpty('accounts');
    }

    public function test_missing_file_is_a_controlled_404_and_does_not_modify_the_draft(): void
    {
        $disk = Storage::fake('wedding_photos');
        [$owner, $wedding] = $this->ownedWedding();
        $this->actingAs($owner)->put($this->saveUrl($wedding), $this->payload(UploadedFile::fake()->image('x.png'), 0, false))->assertSessionHasNoErrors();
        $draft = $wedding->websiteDraft()->sole();
        $disk->delete($draft->hero_photo_path);
        $this->get($draft->heroPhoto()['url'])->assertNotFound();
        $this->assertSame(1, $draft->fresh()->lock_version);
        $disk->assertDirectoryEmpty('accounts');
    }

    public function test_storage_failure_and_transaction_rollback_preserve_the_previous_draft(): void
    {
        $disk = Storage::fake('wedding_photos');
        [$owner, $wedding] = $this->ownedWedding();
        $draft = WebsiteDraft::factory()->for($wedding)->create();
        $before = $draft->fresh()->getAttributes();
        $proxy = Mockery::mock($disk);
        $proxy->shouldReceive('put')->once()->andThrow(new RuntimeException('simulated storage outage'));
        Storage::shouldReceive('disk')->with('wedding_photos')->andReturn($proxy);
        $this->actingAs($owner)->put($this->saveUrl($wedding), $this->payload(UploadedFile::fake()->image('x.png'), 1, false))->assertSessionHasErrors('hero_photo');
        $this->assertSame($before, $draft->fresh()->getAttributes());
        $disk->assertDirectoryEmpty('accounts');
    }

    public function test_database_rollback_removes_new_object_and_preserves_old_object(): void
    {
        $disk = Storage::fake('wedding_photos');
        [$owner, $wedding] = $this->ownedWedding();
        $this->actingAs($owner)->put($this->saveUrl($wedding), $this->payload(UploadedFile::fake()->image('x.png'), 0, false))->assertSessionHasNoErrors();
        $draft = $wedding->websiteDraft()->sole();
        $before = $draft->getAttributes();
        WebsiteDraft::saving(function (): void {
            throw new RuntimeException('simulated database failure');
        });
        $this->put($this->saveUrl($wedding), $this->payload(UploadedFile::fake()->image('new.png'), 1, false))->assertSessionHasErrors('hero_photo');
        $this->assertSame($before, $draft->fresh()->getAttributes());
        $disk->assertCount('accounts', 1, true);
        $disk->assertExists($draft->hero_photo_path);
    }

    public function test_post_commit_delete_failure_does_not_turn_a_successful_save_into_an_error(): void
    {
        $disk = Storage::fake('wedding_photos');
        [$owner, $wedding] = $this->ownedWedding();
        $this->actingAs($owner)->put($this->saveUrl($wedding), $this->payload(UploadedFile::fake()->image('x.png'), 0, false))->assertSessionHasNoErrors();
        $oldPath = $wedding->websiteDraft()->sole()->hero_photo_path;
        $proxy = Mockery::mock($disk);
        $proxy->shouldReceive('delete')->once()->andThrow(new RuntimeException('simulated deletion failure'));
        Storage::shouldReceive('disk')->with('wedding_photos')->andReturn($proxy);
        $this->put($this->saveUrl($wedding), $this->payload(UploadedFile::fake()->image('new.png'), 1, false))->assertSessionHasNoErrors();
        $this->assertSame(2, $wedding->websiteDraft()->sole()->lock_version);
        $disk->assertExists($oldPath);
        $disk->assertCount('accounts', 2, true);
    }

    public function test_insufficient_memory_returns_an_understandable_error_without_persisting(): void
    {
        $disk = Storage::fake('wedding_photos');
        [$owner, $wedding] = $this->ownedWedding();
        $previous = ini_set('memory_limit', '128M');
        try {
            $this->actingAs($owner)->put($this->saveUrl($wedding), $this->payload(UploadedFile::fake()->image('x.png'), 0, false))
                ->assertSessionHasErrors(['hero_photo' => 'Photo uploads are temporarily unavailable. Please contact support.']);
        } finally {
            ini_set('memory_limit', $previous);
        }
        $this->assertDatabaseCount('website_drafts', 0);
        $disk->assertDirectoryEmpty('accounts');
    }

    public function test_uncertain_commit_cleanup_never_deletes_an_object_that_was_committed(): void
    {
        $disk = Storage::fake('wedding_photos');
        [$owner, $wedding] = $this->ownedWedding();
        $connection = DB::connection();
        $attempt = 0;
        DB::partialMock()->shouldReceive('transaction')->twice()->andReturnUsing(function (callable $callback) use ($connection, &$attempt) {
            $result = $connection->transaction($callback);
            if (++$attempt === 1) {
                throw new RuntimeException('simulated lost commit acknowledgement');
            }

            return $result;
        });

        $this->actingAs($owner)->put($this->saveUrl($wedding), $this->payload(UploadedFile::fake()->image('x.png'), 0, false))->assertSessionHasErrors('hero_photo');

        $draft = $wedding->websiteDraft()->sole();
        $this->assertSame(1, $draft->lock_version);
        $disk->assertExists($draft->hero_photo_path);
        $disk->assertCount('accounts', 1, true);
    }

    public function test_transparent_png_stays_transparent_without_upscaling(): void
    {
        $disk = Storage::fake('wedding_photos');
        [$owner, $wedding] = $this->ownedWedding();
        $image = imagecreatetruecolor(40, 20);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        ob_start();
        imagepng($image);
        $file = UploadedFile::fake()->createWithContent('transparent.png', ob_get_clean());

        $this->actingAs($owner)->put($this->saveUrl($wedding), $this->payload($file, 0, false))->assertSessionHasNoErrors();

        $output = imagecreatefromstring($disk->get($wedding->websiteDraft()->sole()->hero_photo_path));
        $this->assertSame(40, imagesx($output));
        $this->assertSame(20, imagesy($output));
        $this->assertSame(127, imagecolorsforindex($output, imagecolorat($output, 0, 0))['alpha']);
        $disk->assertCount('accounts', 1, true);
    }

    #[DataProvider('orientations')]
    public function test_exif_orientation_is_applied_and_metadata_and_appended_content_are_removed(int $orientation, int $width, int $height, int $red, int $green, int $blue): void
    {
        $disk = Storage::fake('wedding_photos');
        [$owner, $wedding] = $this->ownedWedding();
        $fixture = require base_path('tests/Fixtures/wedding-photo.php');
        $file = UploadedFile::fake()->createWithContent('phone.jpg', $fixture($orientation));
        $this->actingAs($owner)->put($this->saveUrl($wedding), $this->payload($file, 0, false))->assertSessionHasNoErrors();
        $bytes = $disk->get($wedding->websiteDraft()->sole()->hero_photo_path);
        $this->assertStringNotContainsString('Exif', $bytes);
        $this->assertStringNotContainsString('GPS_LOCATION_PRIVATE', $bytes);
        $this->assertStringNotContainsString('<script>', $bytes);
        $image = imagecreatefromstring($bytes);
        $this->assertSame($width, imagesx($image));
        $this->assertSame($height, imagesy($image));
        $pixel = imagecolorsforindex($image, imagecolorat($image, 3, 3));
        $this->assertEqualsWithDelta($red, $pixel['red'], 35);
        $this->assertEqualsWithDelta($green, $pixel['green'], 35);
        $this->assertEqualsWithDelta($blue, $pixel['blue'], 35);
        $disk->assertCount('accounts', 1, true);
    }

    public static function orientations(): array
    {
        return [[1, 80, 40, 255, 0, 0], [2, 80, 40, 0, 255, 0], [3, 80, 40, 255, 255, 0], [4, 80, 40, 0, 0, 255],
            [5, 40, 80, 255, 0, 0], [6, 40, 80, 0, 0, 255], [7, 40, 80, 255, 255, 0], [8, 40, 80, 0, 255, 0]];
    }

    private function ownedWedding(): array
    {
        $owner = User::factory()->create();
        $account = Account::create(['name' => 'Photo test workspace']);
        $account->users()->attach($owner, ['role' => 'owner']);
        $wedding = $account->weddings()->create(['partner_one_name' => 'Alex', 'partner_two_name' => 'Jamie', 'public_slug' => fake()->unique()->slug()]);

        return [$owner, $wedding];
    }

    private function saveUrl(Wedding $wedding): string
    {
        return route('accounts.weddings.invitation.update', [$wedding->account_id, $wedding]);
    }

    private function payload(UploadedFile $file, int $version = 0, bool $multipart = true): array
    {
        return ['_method' => 'put', 'content' => $multipart ? json_encode(WebsiteDraft::defaultContent()) : WebsiteDraft::defaultContent(),
            'draft_version' => $version, 'hero_photo_action' => 'replace', 'hero_photo' => $file];
    }
}
