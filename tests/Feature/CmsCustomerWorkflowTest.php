<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\InvitationGroup;
use App\Models\User;
use App\Models\WebsiteDraft;
use App\Models\Wedding;
use App\Services\CmsEditorPreview;
use App\Services\PersonalInvitationLink;
use App\Services\WeddingPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CmsCustomerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['cms_editor.preview_enabled' => true, 'cms_editor.publishing_enabled' => true, 'rsvp.mode' => 'personal']);
    }

    public function test_registration_and_first_wedding_open_the_real_cms_with_real_facts_and_no_implicit_save(): void
    {
        $this->post('/register', ['name' => 'Customer One', 'email' => 'customer@example.test', 'password' => 'CustomerPass123!', 'password_confirmation' => 'CustomerPass123!'])
            ->assertRedirect('/dashboard');
        $owner = User::sole();
        $this->assertAuthenticatedAs($owner);
        $wedding = $this->createWedding();
        $this->assertDatabaseHas('account_members', ['account_id' => $wedding->account_id, 'user_id' => $owner->id, 'role' => 'owner']);
        $this->get('/dashboard')->assertRedirect('/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id);
        $cms = $this->cms($wedding);
        $this->get($this->edit($wedding))->assertRedirect($cms);
        $this->get($cms)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertInertia(fn (Assert $page) => $page
            ->component('weddings/CmsEditorPreview')->where('initialDocument.template_key', 'enchanted-editorial')->where('draftVersion', 0)
            ->where('wedding.partner_one_name', 'Customer Alex')->where('wedding.partner_two_name', 'Customer Jamie')
            ->where('wedding.wedding_date', '2028-04-16')->where('wedding.wedding_time', '15:30')->where('timeZone', 'Asia/Manila')
            ->where('initialDocument.content.photo_slots.hero', null)->where('initialDocument.content.sections.4.data.items', [])
            ->has('initialDocument.content.sections', 9)->where('publishingEnabled', true));
        $this->get('/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id)->assertInertia(fn (Assert $page) => $page
            ->where('cmsEditorUrl', $cms)->where('cmsSavedPreviewUrl', null)->where('publication.draftSaved', false));
        $this->assertDatabaseCount('website_drafts', 0);
        $this->get($cms.'/saved-preview')->assertNotFound();
        $this->postJson('/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/publish', $this->publication($wedding))->assertUnprocessable();
        $this->assertDatabaseCount('website_revisions', 0);
    }

    public function test_existing_account_second_wedding_creation_is_rejected_and_preserves_the_saved_legacy_draft(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $first = $this->createWedding();
        WebsiteDraft::factory()->for($first)->create();
        $legacy = $first->websiteDraft->getAttributes();
        $this->postJson('/accounts/'.$first->account_id.'/weddings', ['partner_one_name' => 'Second Alex', 'partner_two_name' => 'Second Jamie'])->assertConflict();
        $this->assertDatabaseCount('weddings', 1);
        $this->assertSame($legacy, $first->websiteDraft->fresh()->getAttributes());
        $this->assertDatabaseCount('accounts', 1);
        $this->assertDatabaseCount('website_drafts', 1);
    }

    public function test_actual_customer_saves_previews_publishes_and_republishes_only_immutable_saved_content(): void
    {
        $this->actingAs(User::factory()->create());
        $wedding = $this->createWedding();
        $cms = $this->cms($wedding);
        $document = $this->getJson($cms.'/draft')->assertOk()->json('document');
        $document['content']['sections'][0]['data']['message'] = 'Our actual customer invitation';
        $this->putJson($cms.'/draft', ['document' => $document, 'draft_version' => 0])->assertOk()->assertJsonPath('draft_version', 1);
        $this->getJson($cms.'/draft')->assertExactJson(['document' => $document, 'draft_version' => 1]);
        $this->get('/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/preview')->assertRedirect($cms.'/saved-preview');
        $this->get($cms.'/saved-preview')->assertOk()->assertInertia(fn (Assert $page) => $page->component('weddings/CmsSavedPreview')->where('initialDocument', $document));
        $public = '/w/'.$wedding->public_slug;
        $this->get($public)->assertNotFound();
        $publish = '/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/publish';
        $this->postJson($publish, $this->publication($wedding))->assertOk();
        $first = $wedding->fresh()->publishedRevision;
        $this->get($public)->assertOk()->assertSee('Customer Alex')->assertSee('Our actual customer invitation');
        $document['content']['sections'][0]['data']['message'] = 'Private edited invitation';
        $this->putJson($cms.'/draft', ['document' => $document, 'draft_version' => 1])->assertOk()->assertJsonPath('draft_version', 2);
        $this->get($public)->assertSee('Our actual customer invitation')->assertDontSee('Private edited invitation');
        $this->putJson($cms.'/draft', ['document' => $document, 'draft_version' => 0])->assertUnprocessable()->assertJsonValidationErrors('draft_version');
        $this->postJson($publish, $this->publication($wedding))->assertOk();
        $this->get($public)->assertSee('Private edited invitation');
        $this->assertNotSame($first->id, $wedding->fresh()->published_revision_id);
        $this->assertSame('Our actual customer invitation', $first->fresh()->content['sections'][0]['data']['message']);
        $this->assertDatabaseCount('website_revisions', 2);
    }

    public function test_new_customer_can_opt_in_to_photobooth_and_publish_it_for_public_and_personal_visitors(): void
    {
        config(['rsvp.mode' => 'personal']);
        $this->post('/register', ['name' => 'Photobooth Customer', 'email' => 'photobooth-customer@example.test', 'password' => 'CustomerPass123!', 'password_confirmation' => 'CustomerPass123!'])
            ->assertRedirect('/dashboard');
        $owner = User::sole();
        $wedding = $this->createWedding();
        $cms = $this->cms($wedding);
        $document = $this->getJson($cms.'/draft')->assertOk()->json('document');
        $this->assertTrue($document['content']['experience']['enabled']);
        $this->assertSame('photobooth', $document['content']['experience']['intro_style']);
        $this->putJson($cms.'/draft', ['document' => $document, 'draft_version' => 0])->assertOk();
        $public = '/w/'.$wedding->public_slug;
        $this->postJson('/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/publish', $this->publication($wedding))->assertOk();
        $this->get($public)->assertOk()->assertSee('ee-bootstrap-open', false)
            ->assertViewHas('presentation', fn (array $presentation): bool => $presentation['opening']['style'] === 'photobooth'
                && $presentation['wedding']['partner_one_name'] === 'Customer Alex')
            ->assertDontSee('Private RSVP guest');

        $group = InvitationGroup::factory()->for($wedding)->create();
        $guest = $group->guests()->create(['name' => 'Private RSVP guest']);
        $link = app(PersonalInvitationLink::class)->execute($owner, $wedding->fresh(), $group, $guest, 'generate', 0, 0);
        $this->assertSame('active', $link['status']);
        $this->get('/rsvp/'.$link['token'])->assertOk()->assertSee('ee-bootstrap-open', false)
            ->assertViewHas('presentation', fn (array $presentation): bool => $presentation['opening']['style'] === 'photobooth')
            ->assertSee('Private RSVP guest');
        $this->get('/rsvp/'.$link['token'])->assertOk()->assertSee('ee-bootstrap-open', false);
    }

    public function test_stale_legacy_and_cms_tabs_cannot_overwrite_each_others_first_saved_template(): void
    {
        $this->actingAs(User::factory()->create());
        $first = $this->createWedding();
        $document = CmsEditorPreview::document();
        $this->putJson($this->cms($first).'/draft', ['document' => $document, 'draft_version' => 0])->assertOk();
        $this->putJson('/accounts/'.$first->account_id.'/weddings/'.$first->id.'/invitation', ['content' => WebsiteDraft::defaultContent(), 'draft_version' => 0])->assertConflict();
        $this->assertSame(2, $first->websiteDraft()->sole()->schema_version);
        $this->actingAs(User::factory()->create());
        $second = $this->createWedding();
        $this->put('/accounts/'.$second->account_id.'/weddings/'.$second->id.'/invitation', ['content' => WebsiteDraft::defaultContent(), 'draft_version' => 0])->assertSessionHasNoErrors();
        $this->putJson($this->cms($second).'/draft', ['document' => $document, 'draft_version' => 0])->assertConflict();
        $this->assertSame(1, $second->websiteDraft()->sole()->schema_version);
        $this->assertSame(WebsiteDraft::defaultContent(), $second->websiteDraft()->sole()->content);
    }

    public function test_existing_unconfigured_weddings_get_defaults_without_writing_or_converting_saved_legacy_data(): void
    {
        $owner = User::factory()->create();
        $account = Account::create(['name' => 'Existing unconfigured workspace']);
        $account->users()->attach($owner, ['role' => 'owner']);
        $wedding = $account->weddings()->create(['partner_one_name' => 'Unconfigured Alex', 'partner_two_name' => 'Unconfigured Jamie', 'public_slug' => fake()->uuid()]);
        $this->actingAs($owner)->get($this->edit($wedding))->assertRedirect($this->cms($wedding));
        $this->assertDatabaseCount('website_drafts', 0);
        $draft = WebsiteDraft::factory()->for($wedding)->create();
        $before = $draft->fresh()->getAttributes();
        $this->get($this->edit($wedding))->assertOk()->assertInertia(fn (Assert $page) => $page->component('weddings/InvitationEditor'));
        $this->get('/accounts/'.$account->id.'/weddings/'.$wedding->id.'/preview')->assertOk()->assertInertia(fn (Assert $page) => $page->component('weddings/Preview'));
        $this->getJson($this->cms($wedding).'/draft')->assertConflict();
        $this->assertSame($before, $draft->fresh()->getAttributes());
    }

    public function test_legacy_publication_history_without_a_current_draft_does_not_silently_switch_templates(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $wedding = $this->createWedding();
        WebsiteDraft::factory()->for($wedding)->create();
        app(WeddingPublication::class)->publish($wedding, $this->publication($wedding));
        $wedding->websiteDraft()->delete();
        $wedding->forceFill(['published_revision_id' => null])->save();
        $this->get($this->edit($wedding))->assertOk()->assertInertia(fn (Assert $page) => $page->component('weddings/InvitationEditor'));
        $this->assertDatabaseCount('website_drafts', 0);
        $this->assertDatabaseCount('website_revisions', 1);
    }

    public function test_real_customer_entry_points_keep_tenant_and_nested_route_authorization(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $wedding = $this->createWedding();
        $other = User::factory()->create();
        $otherAccount = Account::create(['name' => 'Other customer workspace']);
        $otherAccount->users()->attach($other, ['role' => 'owner']);
        $this->actingAs($other)->get($this->edit($wedding))->assertForbidden();
        $this->getJson($this->cms($wedding))->assertForbidden();
        $this->getJson($this->cms($wedding).'/draft')->assertForbidden();
        $this->putJson($this->cms($wedding).'/draft', ['document' => CmsEditorPreview::document(), 'draft_version' => 0])->assertForbidden();
        $this->get('/accounts/'.$otherAccount->id.'/weddings/'.$wedding->id.'/invitation/edit')->assertNotFound();
        $this->assertDatabaseCount('website_drafts', 0);
        $this->assertDatabaseCount('website_revisions', 0);
    }

    public function test_customer_routing_cannot_activate_cms_in_production_or_override_disabled_configuration(): void
    {
        $this->actingAs(User::factory()->create());
        $wedding = $this->createWedding();
        config(['cms_editor.preview_enabled' => false]);
        $this->get($this->edit($wedding))->assertOk()->assertInertia(fn (Assert $page) => $page->component('weddings/InvitationEditor'));
        $this->getJson($this->cms($wedding))->assertNotFound();
        config(['cms_editor.preview_enabled' => true]);
        $this->app->instance('env', 'production');
        try {
            $this->get($this->edit($wedding))->assertOk()->assertInertia(fn (Assert $page) => $page->component('weddings/InvitationEditor'));
            $this->getJson($this->cms($wedding))->assertNotFound();
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->assertDatabaseCount('website_drafts', 0);
    }

    private function createWedding(): Wedding
    {
        $this->post('/weddings', ['partner_one_name' => 'Customer Alex', 'partner_two_name' => 'Customer Jamie', 'wedding_date' => '2028-04-16', 'wedding_time' => '15:30'])
            ->assertSessionHasNoErrors();

        return Wedding::query()->latest('id')->firstOrFail();
    }

    private function cms(Wedding $wedding): string
    {
        return '/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/cms-preview';
    }

    private function edit(Wedding $wedding): string
    {
        return '/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/invitation/edit';
    }

    private function publication(Wedding $wedding): array
    {
        $summary = app(WeddingPublication::class)->summary($wedding->fresh());

        return ['draft_version' => $summary['draftVersion'], 'publication_version' => $summary['publicationVersion'], 'snapshot_token' => $summary['snapshotToken']];
    }
}
