<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Models\Wedding;
use App\Services\CmsEditorPreview;
use App\Services\WeddingPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsPhotoboothOpeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cms_editor.preview_enabled' => true, 'cms_editor.publishing_enabled' => true]);
        $this->withoutVite();
    }

    public function test_new_documents_start_with_photobooth_and_saved_changes_require_explicit_republish(): void
    {
        [$owner, $wedding, $url] = $this->wedding();
        $document = CmsEditorPreview::document();
        $this->assertSame($this->experience(), $document['content']['experience']);
        $document['content']['experience'] = ['enabled' => false];
        $this->actingAs($owner)->putJson($url.'/draft', ['document' => $document, 'draft_version' => 0])->assertOk();
        $this->publish($wedding);
        $first = $wedding->fresh()->publishedRevision;
        $this->get('/w/'.$wedding->public_slug)->assertOk()->assertViewHas('presentation', fn (array $value): bool => ! isset($value['opening']));
        $document['content']['experience'] = $this->experience();
        $this->putJson($url.'/draft', ['document' => $document, 'draft_version' => 1])->assertOk();
        $this->getJson($url.'/draft')->assertJsonPath('document.content.experience.intro_style', 'photobooth');
        $this->get('/w/'.$wedding->public_slug)->assertViewHas('presentation', fn (array $value): bool => ! isset($value['opening']));
        $this->publish($wedding);
        $this->get('/w/'.$wedding->public_slug)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertViewHas('presentation', fn (array $value): bool => $value['opening'] === [
                'style' => 'photobooth', 'version' => 1, 'weddingKey' => $wedding->public_slug,
                'heading' => 'We are getting married', 'openLabel' => 'Open Invitation',
            ] && $value['wedding']['partner_one_name'] === 'Published Alex' && $value['hero']['photo'] === null)
            ->assertDontSee('personal-rsvp-data', false);
        $this->get('/w/'.$wedding->public_slug)->assertSee('ee-bootstrap-open', false)->assertDontSee('data-bird-beak', false);
        $this->assertSame(['enabled' => false], $first->fresh()->content['experience']);
        $document['content']['experience'] = ['enabled' => false];
        $this->putJson($url.'/draft', ['document' => $document, 'draft_version' => 2])->assertOk();
        $wedding->update(['partner_one_name' => 'Private edited Alex']);
        $this->get('/w/'.$wedding->public_slug)->assertSee('Published Alex')->assertDontSee('Private edited Alex')
            ->assertViewHas('presentation', fn (array $value): bool => isset($value['opening']));
        $this->publish($wedding);
        $this->get('/w/'.$wedding->public_slug)->assertViewHas('presentation', fn (array $value): bool => ! isset($value['opening']));
    }

    public function test_invalid_version_preset_markup_and_other_templates_are_rejected_without_saving(): void
    {
        [$owner, , $url] = $this->wedding();
        $this->actingAs($owner);
        $document = CmsEditorPreview::document();
        $document['content']['experience'] = $this->experience();
        foreach ([
            ['intro_version', 2], ['intro_style', 'unknown-opening'],
            ['intro_settings.variant.print_preset', 'arbitrary-preset'],
            ['intro_settings.font_preset', 'script-serif'], ['intro_settings.palette_preset', 'blush-terracotta'],
            ['intro_settings.opening_text', '<script>alert(1)</script>'],
        ] as [$path, $invalid]) {
            $copy = $document;
            data_set($copy, 'content.experience.'.$path, $invalid);
            $this->putJson($url.'/draft', ['document' => $copy, 'draft_version' => 0])->assertUnprocessable();
        }
        $document['template_key'] = 'floral-trial';
        $this->putJson($url.'/draft', ['document' => $document, 'draft_version' => 0])->assertConflict();
        $this->assertDatabaseCount('website_drafts', 0);
    }

    public function test_reserved_intros_still_cannot_publish_and_another_tenant_cannot_enable_the_intro(): void
    {
        [$owner, $wedding, $url] = $this->wedding();
        $document = CmsEditorPreview::document();
        $document['content']['experience'] = $this->experience();
        $document['content']['experience']['intro_style'] = 'botanical-bloom';
        $document['content']['experience']['intro_settings']['variant'] = ['artwork_preset' => 'botanical-default'];
        $this->actingAs($owner)->putJson($url.'/draft', ['document' => $document, 'draft_version' => 0])->assertOk();
        $summary = app(WeddingPublication::class)->summary($wedding->fresh());
        $this->postJson('/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/publish', [
            'draft_version' => $summary['draftVersion'], 'publication_version' => $summary['publicationVersion'], 'snapshot_token' => $summary['snapshotToken'],
        ])->assertUnprocessable();
        $this->assertDatabaseCount('website_revisions', 0);
        $this->actingAs(User::factory()->create())->putJson($url.'/draft', ['document' => $document, 'draft_version' => 1])->assertForbidden();
        $this->get('/w/'.$wedding->public_slug)->assertNotFound();
    }

    private function wedding(): array
    {
        $owner = User::factory()->create();
        $account = Account::create(['name' => 'Photobooth owner']);
        $account->users()->attach($owner, ['role' => 'owner']);
        $wedding = $account->weddings()->create(['partner_one_name' => 'Published Alex', 'partner_two_name' => 'Published Jamie',
            'wedding_date' => '2028-04-16', 'wedding_time' => '15:30', 'timezone' => 'Asia/Manila', 'public_slug' => (string) Str::uuid()]);

        return [$owner, $wedding, '/accounts/'.$account->id.'/weddings/'.$wedding->id.'/cms-preview'];
    }

    private function experience(): array
    {
        return ['enabled' => true, 'intro_style' => 'photobooth', 'intro_version' => 1,
            'intro_settings' => ['opening_text' => 'We are getting married', 'open_label' => 'Open Invitation',
                'font_preset' => 'editorial-serif', 'palette_preset' => 'ivory-ink', 'variant' => ['print_preset' => 'three-frame']]];
    }

    private function publish(Wedding $wedding): void
    {
        $summary = app(WeddingPublication::class)->summary($wedding->fresh());
        $this->postJson('/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/publish', [
            'draft_version' => $summary['draftVersion'], 'publication_version' => $summary['publicationVersion'], 'snapshot_token' => $summary['snapshotToken'],
        ])->assertOk();
    }
}
