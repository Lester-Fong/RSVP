<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Guest;
use App\Models\InvitationGroup;
use App\Models\User;
use App\Models\Wedding;
use App\Services\CmsDraft;
use App\Services\CmsEditorPreview;
use App\Services\PersonalInvitationLink;
use App\Services\PersonalRsvp;
use App\Services\PersonalRsvpTransaction;
use App\Services\RsvpDelegationManager;
use App\Services\WeddingPublication;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CmsPersonalRsvpTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        $this->app['env'] = 'testing';
        parent::tearDown();
    }

    public function test_personal_snapshot_and_private_state_are_separate_and_generic_pages_never_contain_guest_data(): void
    {
        extract($this->fixture());
        $html = $this->get($path)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee($holder->name)->assertSee($dependent->name)->assertDontSee($independent->name)->assertDontSee('Family contact sentinel')->assertDontSee($token)
            ->assertSee('href="#rsvp">Open your personal RSVP', false)->getContent();
        $this->assertStringNotContainsString($holder->name, $this->script($html, 'enchanted-data'));
        $this->assertStringNotContainsString($dependent->name, $this->script($html, 'enchanted-data'));
        $state = json_decode($this->script($html, 'personal-rsvp-data'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([$holder->name, $dependent->name], array_column($state['initial']['guests'], 'name'));
        $this->assertSame(['pending', 'pending'], array_column($state['initial']['guests'], 'status'));
        $this->assertSame(['reference', 'name', 'version', 'represented', 'status', 'respondedAt'], array_keys($state['initial']['guests'][0]));
        $this->assertStringContainsString('<title>Wedding invitation</title>', $html);
        $this->assertStringContainsString('content="You are invited to celebrate this wedding."', $html);
        $this->assertStringContainsString('https://approved.example.test/w/'.$wedding->public_slug, $html);
        $this->assertStringNotContainsString($holder->name, $wedding->publishedRevision->toJson());
        $public = $this->get('/w/'.$wedding->public_slug)->assertOk()->assertDontSee($holder->name)->assertDontSee($dependent->name)
            ->assertDontSee($independent->name)->assertDontSee('personal-rsvp-data')->assertSee('personal invitation link shared with you')
            ->assertDontSee('href="#rsvp">Open your personal RSVP', false)->assertDontSee($token)->getContent();
        $this->assertStringNotContainsString('<form', $public);
        $this->getJson($path)->assertOk()->assertJsonCount(2, 'rsvp.guests')->assertDontSee('contact_email')->assertDontSee('token_hash');
    }

    public function test_staging_gate_protects_personal_read_and_write_without_changing_invitation_authority(): void
    {
        extract($this->fixture());
        $initial = app(PersonalRsvp::class)->read($token);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->app['env'] = 'staging';
        try {
            $this->get($path)->assertNotFound();
            $this->getJson($path)->assertNotFound();
            $this->postJson($path.'/responses', $this->response($initial, 0, 'attending'))->assertNotFound();
            $this->assertDatabaseCount('rsvp_responses', 0);
            config(['cms_editor.staging_authorized' => true]);
            $this->get($path)->assertOk()->assertSee($holder->name)->assertDontSee($independent->name);
            $this->getJson($path)->assertOk();
            $this->postJson($path.'/responses', $this->response($initial, 0, 'attending'))->assertOk();
            $this->assertDatabaseCount('rsvp_responses', 1);
            $this->app['env'] = 'production';
            $this->get($path)->assertNotFound();
            $this->getJson($path)->assertNotFound();
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_partial_saved_choices_versions_stale_and_foreign_response_attempts_use_the_existing_service(): void
    {
        extract($this->fixture());
        $initial = $this->getJson($path)->json('rsvp');
        $saved = $this->postJson($path.'/responses', $this->response($initial, 0, 'attending'))->assertOk()->assertJsonPath('rsvp.guests.0.status', 'attending')
            ->assertJsonPath('rsvp.guests.0.version', 1)->assertJsonPath('rsvp.guests.1.status', 'pending')->json('rsvp');
        $this->getJson($path)->assertJsonPath('rsvp.guests.0.status', 'attending');
        $this->get($path)->assertSee('Attending')->assertSee('Pending — not answered');
        $this->postJson($path.'/responses', $this->response($initial, 0, 'declined'))->assertUnprocessable()->assertJsonPath('kind', 'stale_response');
        $this->postJson($path.'/responses', $this->response($saved, 0, 'pending'))->assertUnprocessable();
        $foreign = app(PersonalRsvp::class)->read($otherToken);
        $batch = $this->response($saved, 1, 'declined');
        $batch['responses'][] = $this->response($foreign, 0, 'declined')['responses'][0];
        $this->postJson($path.'/responses', $batch)->assertUnprocessable();
        $this->assertDatabaseCount('rsvp_responses', 1);
        $this->postJson($path.'/responses', $this->response($saved, 1, 'declined'))->assertOk()->assertJsonPath('rsvp.guests.1.status', 'declined');
        $this->assertDatabaseCount('rsvp_responses', 2);
    }

    public function test_success_receipt_describes_only_this_committed_submit_and_identical_retries_are_unchanged(): void
    {
        extract($this->fixture());
        $this->freezeTime();
        $snapshot = $wedding->publishedRevision->getAttributes();
        $initial = $this->getJson($path)->assertOk()->assertJsonMissingPath('receipt')->json('rsvp');
        $input = $this->response($initial, 0, 'attending');
        $first = $this->postJson($path.'/responses', $input)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('receipt.changed', true)->assertJsonPath('rsvp.guests.0.status', 'attending')->assertJsonPath('rsvp.guests.1.status', 'pending');
        $this->assertSame(['rsvp', 'receipt'], array_keys($first->json()));
        $this->assertSame(['changed' => true], $first->json('receipt'));
        $this->assertDatabaseHas('rsvp_responses', ['guest_id' => $holder->id, 'status' => 'attending']);
        $saved = $first->json('rsvp');
        $this->travel(1)->minutes();
        $this->postJson($path.'/responses', $input)->assertOk()->assertExactJson(['rsvp' => $saved, 'receipt' => ['changed' => false]]);
        $this->assertSame(1, $holder->fresh()->rsvp_version);
        $this->assertDatabaseCount('rsvp_responses', 1);
        $this->getJson($path)->assertOk()->assertExactJson(['rsvp' => $saved]);
        $this->get('/w/'.$wedding->public_slug)->assertOk()->assertDontSee('personal-rsvp-data')->assertDontSee($holder->name);
        $this->assertSame($snapshot, $wedding->publishedRevision->fresh()->getAttributes());
    }

    public function test_failed_batch_rolls_back_saved_responses_and_never_returns_a_success_receipt(): void
    {
        extract($this->fixture());
        DB::table('guests')->where('id', $dependent->id)->update(['rsvp_version' => PersonalRsvpTransaction::MAX_VERSION]);
        $state = $this->getJson($path)->json('rsvp');
        $batch = $this->response($state, 0, 'attending');
        $batch['responses'][] = $this->response($state, 1, 'attending')['responses'][0];
        $response = $this->postJson($path.'/responses', $batch)->assertUnprocessable();
        $this->assertArrayNotHasKey('receipt', $response->json());
        $this->assertDatabaseCount('rsvp_responses', 0);
        $this->assertSame(0, $holder->fresh()->rsvp_version);
        $this->getJson($path)->assertJsonPath('rsvp.guests.0.status', 'pending');
    }

    public function test_clients_cannot_supply_the_success_receipt(): void
    {
        extract($this->fixture());
        $input = $this->response($this->getJson($path)->json('rsvp'), 0, 'attending');
        $input['receipt'] = ['changed' => true];
        $response = $this->postJson($path.'/responses', $input)->assertUnprocessable();
        $this->assertArrayNotHasKey('receipt', $response->json());
        $this->assertDatabaseCount('rsvp_responses', 0);
    }

    public function test_republication_uses_new_saved_snapshot_without_rotating_credentials_or_changing_answers(): void
    {
        extract($this->fixture());
        $state = app(PersonalRsvp::class)->read($token);
        $this->postJson($path.'/responses', $this->response($state, 0, 'attending'))->assertOk();
        $credential = $holder->invitationLink()->sole()->getAttributes();
        $old = $wedding->publishedRevision;
        $source['content']['sections'][3]['data']['paragraphs'] = ['Private updated story'];
        app(CmsDraft::class)->save($owner, $wedding, $source, 1);
        $wedding->update(['partner_one_name' => 'Draft fact change']);
        $this->get($path)->assertSee('Published snapshot story')->assertDontSee('Private updated story')->assertDontSee('Draft fact change');
        $this->publish($owner, $wedding);
        $this->get($path)->assertSee('Private updated story')->assertSee('Draft fact change')->assertSee('Attending');
        $this->assertSame($credential, $holder->invitationLink()->sole()->getAttributes());
        $this->assertEquals($old->content, $old->fresh()->content);
        $this->assertDatabaseCount('rsvp_responses', 1);
        app(WeddingPublication::class)->unpublish($wedding, 2, $owner);
        $this->get($path)->assertNotFound();
        $this->getJson($path)->assertNotFound();
        $this->postJson($path.'/responses', $this->response($state, 1, 'declined'))->assertNotFound();
        $this->publish($owner, $wedding);
        $this->getJson($path)->assertOk()->assertJsonPath('rsvp.guests.0.status', 'attending');
    }

    public function test_hiding_authored_rsvp_information_preserves_personal_form_without_leaking_hidden_copy(): void
    {
        extract($this->fixture());
        $source['content']['sections'][7]['enabled'] = false;
        $source['content']['sections'][7]['data']['message'] = 'Hidden authored instructions sentinel';
        app(CmsDraft::class)->save($owner, $wedding, $source, 1);
        $this->publish($owner, $wedding);
        $html = $this->get($path)->assertOk()->assertSee('Your RSVP')->assertDontSee('Hidden authored instructions sentinel')->getContent();
        $this->assertStringContainsString('id="personal-rsvp"', $html);
        $this->assertStringContainsString('section:personal-rsvp', $html);
        $this->assertStringContainsString('href="#personal-rsvp">Open your personal RSVP', $html);
        $this->get('/w/'.$wedding->public_slug)->assertOk()->assertDontSee('personal-rsvp-data')->assertDontSee('id="personal-rsvp"', false);
    }

    public function test_access_changes_rotation_and_revocation_revalidate_current_permissions(): void
    {
        extract($this->fixture());
        $before = app(PersonalRsvp::class)->read($token);
        app(RsvpDelegationManager::class)->revoke($owner, $wedding, $group, $dependent, [
            $dependent->id => $dependent->fresh()->rsvp_access_version, $holder->id => $holder->fresh()->rsvp_access_version,
        ], 0);
        $this->postJson($path.'/responses', $this->response($before, 1, 'attending'))->assertUnprocessable()->assertJsonPath('kind', 'stale_access');
        $this->getJson($path)->assertOk()->assertJsonCount(1, 'rsvp.guests')->assertDontSee($dependent->name);
        $new = app(PersonalInvitationLink::class)->execute($owner, $wedding, $group, $holder, 'regenerate', 1, $holder->fresh()->rsvp_access_version);
        $this->get($path)->assertNotFound();
        $this->postJson($path.'/responses', $this->response($before, 0, 'attending'))->assertNotFound();
        $next = '/rsvp/'.$new['token'];
        $this->get($next)->assertOk();
        app(PersonalInvitationLink::class)->execute($owner, $wedding, $group, $holder, 'revoke', 2, $holder->fresh()->rsvp_access_version);
        $this->get($next)->assertNotFound();
        $this->getJson($next)->assertNotFound();
        $this->assertDatabaseCount('rsvp_responses', 0);
    }

    public function test_active_credentials_survive_publishing_and_owner_can_issue_copy_and_revoke_after_publication(): void
    {
        extract($this->fixture());
        $before = $holder->invitationLink()->sole()->getAttributes();
        $this->publish($owner, $wedding);
        $this->assertSame($before, $holder->invitationLink()->sole()->getAttributes());
        $late = Guest::factory()->for($group)->create(['name' => 'Later independent guest']);
        $base = route('accounts.weddings.rsvp-access.credential', [$wedding->account_id, $wedding, $group, $late, 'generate']);
        $url = $this->actingAs($owner)->postJson($base, ['expectedVersion' => 0, 'expectedAccessVersion' => 0])->assertOk()->json('url');
        $this->get($url)->assertOk()->assertSee($late->name)->assertDontSee($holder->name);
        $this->postJson(str_replace('/generate', '/copy', $base), ['expectedVersion' => 1, 'expectedAccessVersion' => 1])->assertJsonPath('url', $url);
        $this->actingAs(User::factory()->create())->postJson($base, ['expectedVersion' => 1, 'expectedAccessVersion' => 1])->assertForbidden();
        $this->actingAs($owner)->postJson(str_replace('/generate', '/revoke', $base), ['expectedVersion' => 1, 'expectedAccessVersion' => 1])->assertOk();
        $this->get($url)->assertNotFound();
    }

    public static function unavailable(): array
    {
        return array_map(fn (string $case): array => [$case], ['template', 'version', 'publishing-off', 'preview-off', 'production']);
    }

    #[DataProvider('unavailable')]
    public function test_unsupported_or_inactive_publication_denies_personal_reads_and_writes(string $case): void
    {
        extract($this->fixture());
        $initial = app(PersonalRsvp::class)->read($token);
        if (in_array($case, ['template', 'version'])) {
            DB::table('website_revisions')->where('id', $wedding->fresh()->published_revision_id)->update([$case === 'template' ? 'template_key' : 'template_version' => $case === 'template' ? 'unsupported' : 99]);
        } elseif ($case === 'production') {
            $this->app['env'] = 'production';
        } else {
            config(['cms_editor.'.($case === 'publishing-off' ? 'publishing_enabled' : 'preview_enabled') => false]);
        }
        $this->get($path)->assertNotFound();
        $this->getJson($path)->assertNotFound();
        $this->postJson($path.'/responses', $this->response($initial, 0, 'attending'))->assertStatus($case === 'production' ? 419 : 404);
        $this->assertDatabaseCount('rsvp_responses', 0);
    }

    public function test_legacy_preview_boilerplate_is_replaced_in_the_personal_slot_without_rewriting_the_snapshot(): void
    {
        extract($this->fixture());
        $source['content']['sections'][7]['data']['message'] = 'Personal RSVP is not connected in this editor preview.';
        app(CmsDraft::class)->save($owner, $wedding, $source, 1);
        $this->publish($owner, $wedding);
        $html = $this->get($path)->assertOk()->getContent();
        $this->assertStringNotContainsString('<p class="ee-rsvp-message">Personal RSVP is not connected', $html);
        $this->assertStringContainsString('Your RSVP', $html);
        $this->assertSame($source['content'], $wedding->fresh()->publishedRevision->content);
    }

    private function fixture(): array
    {
        $this->withoutVite();
        config(['cms_editor.preview_enabled' => true, 'cms_editor.publishing_enabled' => true, 'rsvp.mode' => 'personal', 'app.url' => 'https://approved.example.test']);
        $owner = User::factory()->create();
        $account = Account::create(['name' => 'Disposable DEL04 workspace']);
        $account->users()->attach($owner, ['role' => 'owner']);
        $wedding = $account->weddings()->create(['partner_one_name' => 'Alex', 'partner_two_name' => 'Jamie', 'public_slug' => fake()->uuid(), 'wedding_date' => '2027-05-12', 'wedding_time' => '16:30', 'timezone' => 'Asia/Manila']);
        $source = CmsEditorPreview::document();
        $source['content']['sections'][3]['data']['paragraphs'] = ['Published snapshot story'];
        app(CmsDraft::class)->save($owner, $wedding, $source, 0);
        $group = InvitationGroup::factory()->for($wedding)->create(['label' => 'Private group sentinel', 'contact_email' => 'Family contact sentinel']);
        $holder = Guest::factory()->for($group)->create(['name' => 'Private Holder <strong>One</strong>']);
        $dependent = Guest::factory()->for($group)->create(['name' => 'Private Dependent Two']);
        $independent = Guest::factory()->for($group)->create(['name' => 'Private Independent Three']);
        $links = app(PersonalInvitationLink::class);
        $token = $links->execute($owner, $wedding, $group, $holder, 'generate', 0, 0)['token'];
        $otherToken = $links->execute($owner, $wedding, $group, $independent, 'generate', 0, 0)['token'];
        app(RsvpDelegationManager::class)->assign($owner, $wedding, $group, $dependent, $holder, [$dependent->id => 0, $holder->id => 1], 0);
        $this->publish($owner, $wedding);
        $path = '/rsvp/'.$token;
        $wedding->refresh();

        return compact('owner', 'wedding', 'source', 'group', 'holder', 'dependent', 'independent', 'token', 'otherToken', 'path');
    }

    private function publish(User $owner, Wedding $wedding): void
    {
        $publisher = app(WeddingPublication::class);
        $summary = $publisher->summary($wedding->fresh());
        $publisher->publish($wedding, ['draft_version' => $summary['draftVersion'], 'publication_version' => $summary['publicationVersion'], 'snapshot_token' => $summary['snapshotToken']], $owner);
    }

    private function response(array $state, int $index, string $status): array
    {
        return ['access_version' => $state['access_version'], 'responses' => [['guest' => $state['guests'][$index]['reference'], 'version' => $state['guests'][$index]['version'], 'status' => $status]]];
    }

    private function script(string $html, string $id): string
    {
        preg_match('/<script id="'.$id.'"[^>]*>(.*?)<\/script>/s', $html, $match);

        return $match[1];
    }
}
