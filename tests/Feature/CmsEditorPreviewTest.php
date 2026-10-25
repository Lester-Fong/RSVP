<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Models\WeddingMediaAsset;
use App\Services\CmsEditorPreview;
use App\Services\CmsV2Contract;
use App\Services\UploadWeddingImage;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class CmsEditorPreviewTest extends TestCase
{
    use DatabaseMigrations;

    public function test_default_boundary_denies_every_editor_operation_without_creating_data(): void
    {
        [$owner, $wedding, $url] = $this->fixture(false);
        foreach (['', '/images', '/images/00000000-0000-4000-8000-000000000001'] as $suffix) {
            $this->actingAs($owner)->getJson($url.$suffix)->assertNotFound()->assertHeader('Cache-Control', 'no-store, private');
        }
        foreach (['/images', '/validate'] as $suffix) {
            $this->postJson($url.$suffix, [])->assertNotFound();
        }
        $this->assertDatabaseCount('wedding_media_assets', 0);
        $this->assertDatabaseCount('website_drafts', 0);
    }

    public function test_invalid_configuration_and_production_fail_closed(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        foreach (['true', 'invalid', 1, null, false] as $value) {
            config(['cms_editor.preview_enabled' => $value]);
            $this->actingAs($owner)->getJson($url)->assertNotFound();
        }
        config(['cms_editor.preview_enabled' => true]);
        $this->app->instance('env', 'production');
        try {
            $this->getJson($url)->assertNotFound();
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_missing_schema_fails_closed_instead_of_falling_back(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        Schema::drop('website_revision_media');
        $this->actingAs($owner)->getJson($url)->assertStatus(503)->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_editor_exposes_only_local_document_and_canonical_facts(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        $this->withoutVite();
        $this->actingAs($owner)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('weddings/CmsEditorPreview')
            ->where('wedding.partner_one_name', 'Synthetic')
            ->where('initialDocument.content.experience.enabled', false)
            ->where('initialDocument.content.sections.4.data.items', [])
            ->where('initialDocument.content.sections.2.type', 'countdown')
            ->where('initialDocument.content.sections.2.enabled', true)
            ->where('initialDocument.content.sections.2.data.heading', 'Until we celebrate together')
            ->missing('initialDocument.content.sections.2.data.wedding_date')
            ->where('initialDocument.content.sections.6.type', 'guest_guide')
            ->where('initialDocument.content.sections.6.data.faqs.items.0.question', 'Are my kids welcome?')
            ->where('initialDocument.content.sections.6.data.faqs.items.2.question', 'How do I RSVP?')
            ->where('limits.guestGuideSwatches', CmsV2Contract::MAX_GUEST_GUIDE_SWATCHES)
            ->where('limits.guestGuideFaqs', CmsV2Contract::MAX_GUEST_GUIDE_FAQS)
            ->where('initialImages', [])
            ->where('limits.milestones', CmsV2Contract::MAX_MILESTONES)
            ->missing('assets')->missing('publishedRevision'));
        $this->assertDatabaseCount('website_drafts', 0);
        $this->assertDatabaseCount('website_revisions', 0);
    }

    public function test_real_processor_upload_list_and_owner_image_response_are_private(): void
    {
        $disk = Storage::fake('wedding_media');
        [$owner, $wedding, $url] = $this->fixture();
        $before = $wedding->fresh()->getAttributes();
        $uploaded = $this->actingAs($owner)->postJson($url.'/images', [
            'image' => UploadedFile::fake()->image('private-original.png', 480, 640),
        ])->assertCreated()->assertHeader('Cache-Control', 'no-store, private');
        $image = $uploaded->json('image');
        $this->assertSame(['id', 'kind', 'mime_type', 'byte_size', 'width', 'height', 'preview_url'], array_keys($image));
        $this->assertStringNotContainsString('private-original', $uploaded->getContent());
        $this->assertStringNotContainsString('storage/', $uploaded->getContent());
        $this->assertStringStartsWith('/accounts/', $image['preview_url']);
        $this->getJson($url.'/images')->assertOk()->assertJsonPath('total', 1)->assertJsonCount(1, 'images');
        $bytes = $this->get($image['preview_url'])->assertOk()->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy', 'no-referrer')->getContent();
        $this->assertSame([480, 640, IMAGETYPE_WEBP], array_slice(getimagesizefromstring($bytes), 0, 3));
        $this->assertCount(1, $disk->allFiles());
        $this->assertSame($before, $wedding->fresh()->getAttributes());
        $this->assertDatabaseCount('website_drafts', 0);
        $this->assertDatabaseCount('website_revisions', 0);
        auth()->forgetGuards();
        $this->getJson($image['preview_url'])->assertUnauthorized()->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/storage/wedding-media/'.$image['id'].'.webp')->assertForbidden();
    }

    public function test_unauthenticated_foreign_account_and_manipulated_parent_are_denied(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        $this->getJson($url.'/images')->assertUnauthorized();
        $other = User::factory()->create();
        $this->actingAs($other)->getJson($url.'/images')->assertForbidden();
        $this->postJson($url.'/images', ['image' => UploadedFile::fake()->image('not-yours.png')])->assertForbidden();
        $wrongAccount = Account::create(['name' => 'Other synthetic account']);
        $wrong = '/accounts/'.$wrongAccount->id.'/weddings/'.$wedding->id.'/cms-preview';
        $this->actingAs($owner)->getJson($wrong.'/images')->assertNotFound();
        $this->assertDatabaseCount('wedding_media_assets', 0);
    }

    public function test_same_account_cross_wedding_and_hidden_foreign_media_references_are_rejected(): void
    {
        Storage::fake('wedding_media');
        [$owner, $wedding, $url] = $this->fixture();
        $image = app(UploadWeddingImage::class)->upload($owner, $wedding, UploadedFile::fake()->image('owned.png'));
        $other = $wedding->account->weddings()->create(['partner_one_name' => 'Other', 'partner_two_name' => 'Wedding', 'public_slug' => fake()->uuid()]);
        $otherUrl = '/accounts/'.$wedding->account_id.'/weddings/'.$other->id.'/cms-preview';
        $this->actingAs($owner)->get($otherUrl.'/images/'.$image['id'])->assertNotFound();
        $this->getJson($otherUrl.'/images')->assertJsonPath('total', 0);
        $document = CmsEditorPreview::document();
        $document['template_key'] = 'floral-trial';
        $document['content']['sections'] = array_values(array_filter($document['content']['sections'], fn (array $section): bool => ! in_array($section['type'], ['countdown', 'guest_guide'], true)));
        $document['content']['sections'][3]['enabled'] = false;
        $document['content']['sections'][3]['data']['items'][] = [
            'id' => fake()->uuid(), 'enabled' => false, 'image' => $this->reference($image['id']),
        ];
        $this->postJson($otherUrl.'/validate', ['document' => $document, 'slots' => ['hero' => null, 'story' => null]])
            ->assertUnprocessable();
        $this->assertDatabaseCount('website_drafts', 0);
    }

    public function test_validation_preserves_hidden_content_and_rejects_unknown_fields(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        $document = CmsEditorPreview::document();
        $document['template_key'] = 'floral-trial';
        $document['content']['sections'] = array_values(array_filter($document['content']['sections'], fn (array $section): bool => ! in_array($section['type'], ['countdown', 'guest_guide'], true)));
        $document['content']['sections'][2]['data']['paragraphs'] = ['Our private local edit.'];
        $payload = ['document' => $document, 'slots' => ['hero' => null, 'story' => null]];
        $result = $this->actingAs($owner)->postJson($url.'/validate', $payload)->assertOk();
        $this->assertSame($document, $result->json('document'));
        $this->assertNotContains('story', array_column($result->json('projection.sections'), 'type'));
        $payload['account_id'] = 999;
        $this->postJson($url.'/validate', $payload)->assertUnprocessable()->assertJsonValidationErrors('request');
        unset($payload['account_id']);
        $payload['document']['content']['sections'][2]['data']['heading'] = '<script>invalid</script>';
        $this->postJson($url.'/validate', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('website_drafts', 0);
        $this->assertDatabaseCount('website_revisions', 0);
    }

    public function test_milestone_photo_references_preserve_order_visibility_and_private_ownership(): void
    {
        Storage::fake('wedding_media');
        [$owner, $wedding, $url] = $this->fixture();
        $photo = app(UploadWeddingImage::class)->upload($owner, $wedding, UploadedFile::fake()->image('synthetic-moment.png'));
        $document = CmsEditorPreview::document();
        $document['content']['sections'][3]['data']['milestones'] = [
            ['id' => fake()->uuid(), 'enabled' => true, 'title' => 'Our first trip', 'description' => 'Together.',
                'when' => ['precision' => 'date', 'value' => '2024-04-15'], 'photo' => $this->reference($photo['id'])],
            ['id' => fake()->uuid(), 'enabled' => false, 'title' => 'A private draft moment',
                'when' => null, 'photo' => $this->reference($photo['id'])],
        ];
        $payload = ['document' => $document, 'slots' => ['hero' => null, 'story' => null, 'details' => null]];
        $this->actingAs($owner)->postJson($url.'/validate', $payload)->assertOk()->assertJsonPath('document', $document);
        $this->assertDatabaseCount('website_drafts', 0);
        $this->assertDatabaseCount('website_revisions', 0);
        $this->assertDatabaseCount('wedding_media_assets', 1);
        $other = $wedding->account->weddings()->create(['partner_one_name' => 'Other', 'partner_two_name' => 'Synthetic', 'public_slug' => fake()->uuid()]);
        $foreign = WeddingMediaAsset::factory()->for($other)->create();
        $payload['document']['content']['sections'][3]['data']['milestones'][1]['photo'] = $this->reference($foreign->id);
        $this->postJson($url.'/validate', $payload)->assertUnprocessable();
        auth()->forgetGuards();
        $this->getJson($url.'/images/'.$photo['id'])->assertUnauthorized()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_independent_preview_photo_slots_remain_owned_local_assignments_only(): void
    {
        Storage::fake('wedding_media');
        [$owner, $wedding, $url] = $this->fixture();
        $photo = app(UploadWeddingImage::class)->upload($owner, $wedding, UploadedFile::fake()->image('synthetic.png'));
        $slots = ['hero' => $this->reference($photo['id']), 'details' => $this->reference($photo['id']), 'story' => null];
        $payload = ['document' => CmsEditorPreview::document(), 'slots' => $slots];
        $this->actingAs($owner)->postJson($url.'/validate', $payload)->assertOk();
        $this->assertDatabaseCount('website_drafts', 0);
        $this->assertDatabaseCount('website_revisions', 0);
        $this->assertDatabaseCount('wedding_media_assets', 1);
        $other = $wedding->account->weddings()->create(['partner_one_name' => 'Other', 'partner_two_name' => 'Synthetic', 'public_slug' => fake()->uuid()]);
        $foreign = WeddingMediaAsset::factory()->for($other)->create();
        $payload['slots']['details'] = $this->reference($foreign->id);
        $this->postJson($url.'/validate', $payload)->assertUnprocessable();
        $payload['slots']['details'] = null;
        $payload['slots']['arbitrary'] = null;
        $this->postJson($url.'/validate', $payload)->assertUnprocessable();
    }

    public function test_invalid_images_and_client_storage_metadata_never_write_assets(): void
    {
        $disk = Storage::fake('wedding_media');
        [$owner, $wedding, $url] = $this->fixture();
        $this->actingAs($owner)->postJson($url.'/images', ['image' => UploadedFile::fake()->createWithContent('invalid.png', 'not image')])->assertUnprocessable();
        $this->postJson($url.'/images', ['image' => UploadedFile::fake()->image('valid.png'), 'disk' => 'public'])->assertUnprocessable()->assertJsonValidationErrors('request');
        $this->assertCount(0, $disk->allFiles());
        $this->assertDatabaseCount('wedding_media_assets', 0);
    }

    public function test_actual_csrf_middleware_protects_upload_and_validation(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        $app = $this->app;
        $this->app->bind(ValidateCsrfToken::class, fn () => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $this->actingAs($owner)->postJson($url.'/images', [])->assertStatus(419)->assertHeader('Cache-Control', 'no-store, private');
        $this->postJson($url.'/validate', [])->assertStatus(419);
        $this->putJson($url.'/draft', ['document' => CmsEditorPreview::document(), 'draft_version' => 0])->assertStatus(419);
        $this->assertDatabaseCount('website_drafts', 0);
        $this->assertDatabaseCount('wedding_media_assets', 0);
    }

    public function test_failed_storage_returns_safe_error_and_retry_succeeds(): void
    {
        $disk = Storage::fake('wedding_media');
        $proxy = Mockery::mock($disk);
        $attempt = 0;
        $proxy->shouldReceive('put')->twice()->andReturnUsing(function (string $path, string $bytes, array $options) use ($disk, &$attempt): bool {
            return ++$attempt === 1 ? false : $disk->put($path, $bytes, $options);
        });
        Storage::shouldReceive('disk')->with('wedding_media')->andReturn($proxy);
        [$owner, $wedding, $url] = $this->fixture();
        $bad = $this->actingAs($owner)->postJson($url.'/images', ['image' => UploadedFile::fake()->image('private-filename.png')])->assertUnprocessable();
        $this->assertStringNotContainsString('private-filename', $bad->getContent());
        $this->assertDatabaseCount('wedding_media_assets', 0);
        $this->postJson($url.'/images', ['image' => UploadedFile::fake()->image('retry.png')])->assertCreated();
        $this->assertDatabaseCount('wedding_media_assets', 1);
        $this->assertCount(1, $disk->allFiles());
    }

    public function test_genuine_upload_throttle_does_not_consume_read_or_validation_allowance(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        for ($index = 0; $index < 15; $index++) {
            $this->actingAs($owner)->postJson($url.'/images', [])->assertUnprocessable();
        }
        $limited = $this->postJson($url.'/images', [])->assertStatus(429)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertGreaterThan(0, (int) $limited->headers->get('Retry-After'));
        $this->getJson($url.'/images')->assertOk();
        $this->postJson($url.'/validate', ['document' => CmsEditorPreview::document(), 'slots' => ['hero' => null, 'story' => null]])->assertOk();
    }

    public function test_library_is_paginated_and_does_not_include_another_weddings_assets(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        WeddingMediaAsset::factory()->count(15)->for($wedding)->create();
        $this->actingAs($owner)->getJson($url.'/images')->assertOk()->assertJsonCount(12, 'images')->assertJsonPath('total', 15)->assertJsonPath('lastPage', 2);
        $this->getJson($url.'/images?page=2')->assertOk()->assertJsonCount(3, 'images');
        $this->getJson($url.'/images?page=-1')->assertUnprocessable();
    }

    public function test_empty_strings_are_preserved_but_explicit_invalid_types_remain_rejected(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        $document = CmsEditorPreview::document();
        $payload = ['document' => $document, 'slots' => ['hero' => null, 'story' => null]];
        $this->actingAs($owner)->postJson($url.'/validate', $payload)->assertOk()
            ->assertJsonPath('document.content.sections.1.data.events.0.address', '');
        $payload['document']['content']['sections'][1]['data']['events'][0]['address'] = null;
        $this->postJson($url.'/validate', $payload)->assertUnprocessable();
    }

    public function test_gallery_ten_image_boundary_and_duplicates_are_validated_through_authenticated_http(): void
    {
        Storage::fake('wedding_media');
        [$owner, $wedding, $url] = $this->fixture();
        $document = CmsEditorPreview::document();
        $items = [];
        for ($index = 0; $index < 10; $index++) {
            $image = app(UploadWeddingImage::class)->upload($owner, $wedding, UploadedFile::fake()->image('synthetic.png', 16, 16));
            $items[] = ['id' => fake()->uuid(), 'enabled' => true, 'image' => $this->reference($image['id'])];
        }
        $document['content']['sections'][4]['data']['items'] = $items;
        $payload = ['document' => $document, 'slots' => ['hero' => $items[0]['image'], 'story' => null]];
        $this->actingAs($owner)->postJson($url.'/validate', $payload)->assertOk()
            ->assertJsonCount(10, 'projection.sections.4.data.items');
        $payload['document']['content']['sections'][4]['data']['items'][] = ['id' => fake()->uuid(), 'enabled' => true, 'image' => $this->reference(fake()->uuid())];
        $this->postJson($url.'/validate', $payload)->assertUnprocessable()->assertJsonValidationErrors('content.sections.4.data.items');
        $payload['document']['content']['sections'][4]['data']['items'] = [$items[0], [...$items[0], 'id' => fake()->uuid()]];
        $this->postJson($url.'/validate', $payload)->assertUnprocessable()->assertJsonValidationErrors('content.sections.4.data.items.0.image.asset_id');
        $this->assertDatabaseCount('website_drafts', 0);
        $this->assertDatabaseCount('website_revisions', 0);
        $this->assertDatabaseCount('wedding_media_assets', 10);
    }

    private function fixture(bool $enabled = true): array
    {
        config(['cms_editor.preview_enabled' => $enabled]);
        $owner = User::factory()->create();
        $account = Account::create(['name' => 'Synthetic editor workspace']);
        $account->users()->attach($owner, ['role' => 'owner']);
        $wedding = $account->weddings()->create(['partner_one_name' => 'Synthetic', 'partner_two_name' => 'Couple', 'public_slug' => fake()->uuid()]);

        return [$owner, $wedding, '/accounts/'.$account->id.'/weddings/'.$wedding->id.'/cms-preview'];
    }

    private function reference(string $id): array
    {
        return ['asset_id' => $id, 'alt' => 'Synthetic photograph', 'focal' => 'center', 'crop' => 'natural'];
    }
}
