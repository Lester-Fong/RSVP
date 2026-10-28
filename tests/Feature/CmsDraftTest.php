<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Models\WebsiteDraft;
use App\Models\WebsiteRevision;
use App\Models\Wedding;
use App\Models\WeddingMediaAsset;
use App\Services\CmsEditorPreview;
use App\Services\CmsV2Contract;
use App\Services\UploadWeddingImage;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CmsDraftTest extends TestCase
{
    use DatabaseMigrations;

    public function test_complete_source_slots_and_media_survive_reload_without_publication(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        $image = $this->image($owner, $wedding);
        $document = CmsEditorPreview::document();
        $document['content']['photo_slots'] = ['hero' => $image, 'details' => [...$image, 'crop' => 'landscape'], 'story' => [...$image, 'focal' => 'top']];
        $document['content']['sections'][3]['data'] = ['heading' => 'Our story', 'paragraphs' => ['Together.'], 'milestones' => [
            ['id' => fake()->uuid(), 'enabled' => false, 'title' => 'Hidden moment', 'when' => ['precision' => 'year', 'value' => 2020], 'photo' => $image]]];
        $document['content']['sections'][4]['data']['items'] = [['id' => fake()->uuid(), 'enabled' => true, 'image' => $image, 'caption' => 'Our photo']];
        $document['content']['sections'][5]['data']['groups'] = [['id' => fake()->uuid(), 'enabled' => false, 'title' => 'Loved ones', 'people' => [['id' => fake()->uuid(), 'name' => 'A loved one']]]];
        $document['content']['sections'] = array_reverse($document['content']['sections']);
        $this->actingAs($owner)->putJson($url.'/draft', ['document' => $document, 'draft_version' => 0])->assertOk()->assertJsonPath('draft_version', 1)->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson($url.'/draft')->assertExactJson(['document' => $document, 'draft_version' => 1]);
        $this->withoutVite();
        $this->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->where('initialDocument', $document)->where('draftVersion', 1)->where('timeZone', 'Asia/Manila')->has('initialImages', 1));
        $this->get($url.'/saved-preview')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')->assertInertia(fn (Assert $p) => $p->component('weddings/CmsSavedPreview')->where('initialDocument', $document));
        $this->assertDatabaseCount('website_revisions', 0);
        $this->assertNull($wedding->fresh()->published_revision_id);
    }

    public function test_stale_requests_and_template_transitions_preserve_the_winning_draft(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        $doc = CmsEditorPreview::document();
        $this->actingAs($owner)->putJson($url.'/draft', ['document' => $doc, 'draft_version' => 0])->assertOk();
        $changed = $doc;
        $changed['content']['sections'][3]['data']['heading'] = 'Another window';
        $this->putJson($url.'/draft', ['document' => $changed, 'draft_version' => 0])->assertUnprocessable()->assertJsonValidationErrors('draft_version');
        $this->getJson($url.'/draft')->assertJsonPath('document', $doc)->assertJsonPath('draft_version', 1);
        $this->putJson($url.'/draft', ['document' => $changed, 'draft_version' => 1])->assertOk()->assertJsonPath('draft_version', 2);
        $changed['template_key'] = 'floral-trial';
        $this->putJson($url.'/draft', ['document' => $changed, 'draft_version' => 2])->assertStatus(409);
        $this->assertSame('enchanted-editorial', $wedding->websiteDraft()->first()->template_key);
    }

    public function test_v1_draft_cannot_be_replaced_or_exposed(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        $draft = WebsiteDraft::factory()->for($wedding)->create();
        $before = $draft->fresh()->getRawOriginal();
        $this->actingAs($owner)->putJson($url.'/draft', ['document' => CmsEditorPreview::document(), 'draft_version' => $draft->lock_version])->assertStatus(409);
        $this->getJson($url.'/draft')->assertStatus(409)->assertJsonMissingPath('document');
        $this->assertSame($before, $draft->fresh()->getRawOriginal());
    }

    public function test_old_v2_source_without_slots_is_preserved_exactly(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        $doc = CmsEditorPreview::document();
        unset($doc['content']['photo_slots']);
        $this->actingAs($owner)->putJson($url.'/draft', ['document' => $doc, 'draft_version' => 0])->assertOk();
        $this->getJson($url.'/draft')->assertExactJson(['document' => $doc, 'draft_version' => 1]);
        $this->assertSame($doc['content'], $wedding->websiteDraft()->first()->content);
    }

    public function test_foreign_hidden_media_and_invalid_payloads_do_not_write(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        $other = $wedding->account->weddings()->create(['partner_one_name' => 'Other', 'partner_two_name' => 'Couple', 'public_slug' => fake()->uuid()]);
        $foreign = $this->image($owner, $other);
        $doc = CmsEditorPreview::document();
        $doc['content']['sections'][3]['enabled'] = false;
        foreach ([$foreign, [...$foreign, 'asset_id' => fake()->uuid()]] as $ref) {
            $doc['content']['photo_slots']['story'] = $ref;
            $this->actingAs($owner)->putJson($url.'/draft', ['document' => $doc, 'draft_version' => 0])->assertUnprocessable();
        }
        $payload = ['document' => CmsEditorPreview::document(), 'draft_version' => 0];
        foreach ([['wedding_id' => 999], ['draft_version' => '0'], ['draft_version' => -1], ['draft_version' => 4294967295]] as $change) {
            $this->putJson($url.'/draft', [...$payload, ...$change])->assertUnprocessable();
        }
        foreach ([['hero' => null], ['hero' => null, 'details' => null, 'story' => null, 'path' => 'private']] as $slots) {
            $payload['document']['content']['photo_slots'] = $slots;
            $this->putJson($url.'/draft', $payload)->assertUnprocessable();
        }
        $this->assertDatabaseCount('website_drafts', 0);
    }

    public function test_anonymous_foreign_account_and_nested_wedding_access_is_denied(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        foreach ([$url, $url.'/draft', $url.'/saved-preview'] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
        $stranger = User::factory()->create();
        foreach ([$url, $url.'/draft', $url.'/saved-preview'] as $path) {
            $this->actingAs($stranger)->getJson($path)->assertForbidden();
        }
        $payload = ['document' => CmsEditorPreview::document(), 'draft_version' => 0];
        $this->putJson($url.'/draft', $payload)->assertForbidden();
        $other = Account::create(['name' => 'Other']);
        $other->users()->attach($owner, ['role' => 'owner']);
        $this->actingAs($owner)->putJson(str_replace('/accounts/'.$wedding->account_id.'/', '/accounts/'.$other->id.'/', $url).'/draft', $payload)->assertNotFound();
        $this->assertDatabaseCount('website_drafts', 0);
    }

    public function test_failure_after_insert_rolls_back_without_erasing_assets(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        $doc = CmsEditorPreview::document();
        $doc['content']['photo_slots']['hero'] = $this->image($owner, $wedding);
        Event::listen('eloquent.saved: '.WebsiteDraft::class, function (): void {
            throw new \RuntimeException('Synthetic failure');
        });
        try {
            $this->actingAs($owner)->putJson($url.'/draft', ['document' => $doc, 'draft_version' => 0])->assertStatus(500);
        } finally {
            Event::forget('eloquent.saved: '.WebsiteDraft::class);
        }
        $this->assertDatabaseCount('website_drafts', 0);
        $this->assertDatabaseCount('wedding_media_assets', 1);
        $this->assertCount(1, Storage::disk('wedding_media')->allFiles());
    }

    public function test_live_snapshot_and_public_response_are_unchanged_by_premium_save(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        $this->withoutVite();
        $revision = WebsiteRevision::factory()->for($wedding)->create();
        $wedding->forceFill(['published_revision_id' => $revision->id, 'publication_version' => 1])->save();
        $before = $revision->fresh()->getRawOriginal();
        $public = '/w/'.$wedding->public_slug;
        $html = $this->get($public)->assertOk()->getContent();
        $this->actingAs($owner)->putJson($url.'/draft', ['document' => CmsEditorPreview::document(), 'draft_version' => 0])->assertOk();
        $this->assertSame($before, $revision->fresh()->getRawOriginal());
        $this->assertSame($revision->id, $wedding->fresh()->published_revision_id);
        $this->assertSame(1, $wedding->fresh()->publication_version);
        $this->assertSame($html, $this->get($public)->assertOk()->getContent());
    }

    public function test_disabled_or_missing_schema_fails_closed(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        $payload = ['document' => CmsEditorPreview::document(), 'draft_version' => 0];
        config(['cms_editor.preview_enabled' => false]);
        $this->actingAs($owner)->putJson($url.'/draft', $payload)->assertNotFound();
        config(['cms_editor.preview_enabled' => true]);
        Schema::table('weddings', fn ($t) => $t->dropColumn('timezone'));
        try {
            $this->getJson($url.'/draft')->assertStatus(503);
            $this->putJson($url.'/draft', $payload)->assertStatus(503);
        } finally {
            $migration = require database_path('migrations/2026_10_21_162436_add_timezone_to_weddings_table.php');
            $migration->up();
        }
        $this->assertDatabaseCount('website_drafts', 0);
    }

    public function test_timezone_upgrade_and_canonical_edit_preserve_date_and_drafts(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        $migration = require database_path('migrations/2026_10_21_162436_add_timezone_to_weddings_table.php');
        $migration->down();
        $migration->up();
        $this->assertSame('Asia/Manila', $wedding->fresh()->timezone);
        $attrs = ['partner_one_name' => 'A', 'partner_two_name' => 'B', 'wedding_date' => '2027-05-12', 'wedding_time' => '16:30', 'timezone' => 'America/New_York'];
        $route = '/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id;
        $this->actingAs($owner)->put($route, $attrs)->assertRedirect();
        $this->assertSame('America/New_York', $wedding->fresh()->timezone);
        $this->assertSame('2027-05-12', $wedding->fresh()->wedding_date->format('Y-m-d'));
        $this->putJson($route, [...$attrs, 'timezone' => '+08:00'])->assertUnprocessable()->assertJsonValidationErrors('timezone');
        $this->assertDatabaseCount('website_drafts', 0);
    }

    public function test_hidden_slot_projection_keeps_complete_validated_source(): void
    {
        $doc = CmsEditorPreview::document();
        $ref = ['asset_id' => fake()->uuid(), 'alt' => 'Photo', 'focal' => 'center', 'crop' => 'portrait'];
        $doc['content']['photo_slots'] = ['hero' => $ref, 'details' => $ref, 'story' => $ref];
        $doc['content']['sections'][0]['enabled'] = false;
        $doc['content']['sections'][3]['enabled'] = false;
        $projection = app(CmsV2Contract::class)->project($doc);
        $this->assertNull($projection['photo_slots']['hero']);
        $this->assertNull($projection['photo_slots']['story']);
        $this->assertSame($ref, $projection['photo_slots']['details']);
        $this->assertSame($ref, app(CmsV2Contract::class)->validate($doc)['content']['photo_slots']['story']);
    }

    public function test_saved_media_outside_library_page_one_is_resolved_and_missing_bytes_fail_without_erasure(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        $ref = $this->image($owner, $wedding);
        WeddingMediaAsset::factory()->for($wedding)->count(14)->create(['created_at' => now()->addDay()]);
        $doc = CmsEditorPreview::document();
        $doc['content']['photo_slots']['hero'] = $ref;
        $this->actingAs($owner)->putJson($url.'/draft', ['document' => $doc, 'draft_version' => 0])->assertOk();
        $library = $this->getJson($url.'/images')->assertOk()->json('images');
        $this->assertNotContains($ref['asset_id'], array_column($library, 'id'));
        $this->withoutVite();
        $this->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->has('initialImages', 1)->where('initialImages.0.id', $ref['asset_id']));
        $asset = $wedding->mediaAssets()->findOrFail($ref['asset_id']);
        Storage::disk('wedding_media')->delete($asset->path);
        $this->getJson($url.'/draft')->assertUnprocessable()->assertJsonMissingPath('document');
        $this->assertSame($doc['content'], $wedding->websiteDraft()->first()->content);
        $this->assertSame(1, $wedding->websiteDraft()->first()->lock_version);
    }

    public function test_save_limit_returns_retry_after_without_exhausting_preview_validation_or_reads(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        $this->actingAs($owner);
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->putJson($url.'/draft', [])->assertUnprocessable();
        }
        $response = $this->putJson($url.'/draft', [])->assertStatus(429)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->getJson($url.'/draft')->assertOk();
        $this->postJson($url.'/validate', ['document' => CmsEditorPreview::document(), 'slots' => ['hero' => null, 'details' => null, 'story' => null]])->assertOk();
        $this->assertDatabaseCount('website_drafts', 0);
    }

    public function test_frame_and_slideshow_settings_persist_without_overriding_saved_visibility(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        $image = $this->image($owner, $wedding);
        $document = CmsEditorPreview::document();
        $this->assertTrue($document['content']['sections'][4]['enabled']);
        $document['content']['photo_slots']['hero'] = [...$image, 'frame' => 'fit'];
        $document['content']['photo_slots']['details'] = [...$image, 'frame' => 'fill'];
        $document['content']['sections'][4]['enabled'] = false;
        $document['content']['sections'][4]['data']['items'] = [['id' => fake()->uuid(), 'enabled' => true, 'image' => [...$image, 'frame' => 'fit']]];
        $document['content']['sections'][4]['data']['slideshow'] = ['autoplay' => false, 'interval_seconds' => 8];
        $this->actingAs($owner)->putJson($url.'/draft', ['document' => $document, 'draft_version' => 0])->assertOk();
        $this->getJson($url.'/draft')->assertExactJson(['document' => $document, 'draft_version' => 1]);
        $this->withoutVite();
        $this->get($url.'/saved-preview')->assertInertia(fn (Assert $page) => $page->where('initialDocument', $document));
        $this->assertNull($wedding->fresh()->published_revision_id);
    }

    public function test_invalid_frame_and_slideshow_settings_cannot_replace_the_draft(): void
    {
        [$owner, $wedding, $url] = $this->fixture();
        $document = CmsEditorPreview::document();
        $this->actingAs($owner)->putJson($url.'/draft', ['document' => $document, 'draft_version' => 0])->assertOk();
        $image = $this->image($owner, $wedding);
        foreach ([['autoplay' => 'true', 'interval_seconds' => 5], ['autoplay' => true, 'interval_seconds' => 0], ['autoplay' => true, 'interval_seconds' => 5.5], ['autoplay' => true, 'interval_seconds' => 5, 'script' => 'no']] as $settings) {
            $invalid = $document;
            $invalid['content']['sections'][4]['data']['slideshow'] = $settings;
            $this->putJson($url.'/draft', ['document' => $invalid, 'draft_version' => 1])->assertUnprocessable();
        }
        $invalid = $document;
        $invalid['content']['photo_slots']['hero'] = [...$image, 'frame' => 'stretch'];
        $this->putJson($url.'/draft', ['document' => $invalid, 'draft_version' => 1])->assertUnprocessable()->assertJsonValidationErrors('content.photo_slots.hero.frame');
        $this->getJson($url.'/draft')->assertExactJson(['document' => $document, 'draft_version' => 1]);
    }

    private function fixture(): array
    {
        config(['cms_editor.preview_enabled' => true]);
        Storage::fake('wedding_media');
        $owner = User::factory()->create();
        $account = Account::create(['name' => 'Synthetic draft workspace']);
        $account->users()->attach($owner, ['role' => 'owner']);
        $wedding = $account->weddings()->create(['partner_one_name' => 'Alex', 'partner_two_name' => 'Jamie', 'public_slug' => fake()->uuid()]);

        return [$owner, $wedding, '/accounts/'.$account->id.'/weddings/'.$wedding->id.'/cms-preview'];
    }

    private function image(User $owner, Wedding $wedding): array
    {
        $asset = app(UploadWeddingImage::class)->upload($owner, $wedding, UploadedFile::fake()->image('synthetic.png', 64, 96));

        return ['asset_id' => $asset['id'], 'alt' => 'Synthetic photo', 'focal' => 'center', 'crop' => 'natural'];
    }
}
