<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Guest;
use App\Models\InvitationGroup;
use App\Models\RsvpResponse;
use App\Models\User;
use App\Models\WebsiteDraft;
use App\Models\Wedding;
use App\Services\InvitationEligibility;
use App\Services\InvitationLink;
use App\Services\PersonalRsvpTransaction;
use App\Services\RedactInvitationTokens;
use App\Services\RsvpMode;
use App\Services\WeddingAttendance;
use App\Services\WeddingPublication;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PersonalRsvpHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_withdrawal_and_reinstatement_are_private_preserve_answers_and_filter_attendance(): void
    {
        config(['rsvp.mode' => 'personal', 'rsvp.withdrawal_enabled' => true]);
        $this->app->forgetInstance(InvitationEligibility::class);
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        $this->actingAs($owner);
        $target = $guests[0];
        RsvpResponse::factory()->for($target)->create(['status' => 'attending', 'responded_at' => '2026-10-01 12:00:00']);
        $token = $this->credential($wedding, $group, $target, 'generate')->assertOk()->json('url');
        $base = $this->base($wedding, $group).'/guests/'.$target->id.'/invitation';

        $inspection = $this->getJson($base.'/withdraw')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('confirmation.status', 'attending')
            ->assertJsonPath('confirmation.affected.0.name', 'Juan');
        $this->postJson($base.'/withdraw', $inspection->json('confirmation.confirmation'))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('result.state', 'withdrawn');
        $this->assertSame('attending', $target->fresh()->rsvpResponse->status);
        $this->get($token)->assertNotFound();
        $this->postJson($base.'/withdraw', $inspection->json('confirmation.confirmation'))->assertStatus(422)->assertJsonPath('kind', 'stale_access');

        $this->get($this->guestPage($wedding))->assertInertia(fn (Assert $page) => $page
            ->where('withdrawalEnabled', true)->where('totals.active_people', 3)->where('totals.withdrawn_people', 1)
            ->where('groups.data.0.guests_count', 3));
        $this->get($this->guestPage($wedding).'?invitation_state=withdrawn')->assertInertia(fn (Assert $page) => $page
            ->where('groups.data.0.guests.0.invitation_state', 'withdrawn')->where('groups.data.0.guests.0.rsvp_status', 'attending'));
        $this->get('/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/attendance')->assertInertia(fn (Assert $page) => $page
            ->where('withdrawalEnabled', true)->where('summary.invited', 3)->where('summary.attending', 0));

        $this->postJson($this->base($wedding, $group).'/guests/'.$target->id.'/credential/generate', [
            'expectedVersion' => $target->fresh()->invitationLink->version,
            'expectedAccessVersion' => $target->fresh()->rsvp_access_version,
        ])->assertStatus(422);
        $reinstate = $this->getJson($base.'/reinstate')->assertOk()->assertJsonPath('confirmation.status', 'attending');
        $this->postJson($base.'/reinstate', $reinstate->json('confirmation.confirmation'))->assertOk()->assertJsonPath('result.state', 'active');
        $this->assertSame('attending', $target->fresh()->rsvpResponse->status);
        $this->assertNull($target->fresh()->rsvpDelegation?->representative_guest_id);
        $this->get($token)->assertNotFound();

        foreach ($guests as $guest) {
            $guestLifecycle = $this->getJson($this->base($wedding, $group).'/guests/'.$guest->id.'/invitation/withdraw')->assertOk()->json('confirmation.confirmation');
            $this->postJson($this->base($wedding, $group).'/guests/'.$guest->id.'/invitation/withdraw', $guestLifecycle)->assertOk();
        }
        $this->get($this->guestPage($wedding))->assertInertia(fn (Assert $page) => $page->where('groups.data', []));
        $this->get($this->guestPage($wedding).'?invitation_state=all')->assertInertia(fn (Assert $page) => $page
            ->where('totals.groups', 1)->where('groups.data.0.guests_count', 4)->has('groups.data.0.guests', 4));
        $this->get('/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/attendance')->assertInertia(fn (Assert $page) => $page
            ->where('summary.invited', 0)->where('summary.attending', 0));
    }

    public function test_withdrawal_owner_routes_are_tenant_scoped_and_hidden_when_gate_is_off(): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        $base = $this->base($wedding, $group).'/guests/'.$guests[0]->id.'/invitation/withdraw';
        $this->actingAs($owner)->getJson($base)->assertNotFound();

        config(['rsvp.withdrawal_enabled' => true]);
        $this->app->forgetInstance(InvitationEligibility::class);
        [$otherOwner, $otherWedding, $otherGroup, $otherGuests] = $this->fixture();
        $this->actingAs($otherOwner)->getJson($base)->assertForbidden();
        $this->assertNull($guests[0]->fresh()->withdrawn_at);
    }

    public function test_personal_is_default_and_missing_schema_fails_closed(): void
    {
        [$owner, $wedding, $group] = $this->fixture();
        $this->assertSame('personal', app(RsvpMode::class)->mode());
        $this->actingAs($owner)->get($this->guestPage($wedding))->assertOk();
        Schema::drop('guest_invitation_links');
        $this->get('/rsvp/'.str_repeat('a', 64))->assertStatus(503)->assertHeader('Referrer-Policy', 'no-referrer');
        $this->getJson($this->base($wedding, $group))->assertStatus(503);
        $this->get($this->guestPage($wedding))->assertStatus(503);
        $this->get('/invite/'.str_repeat('a', 64))->assertNotFound();
    }

    #[DataProvider('invalidModes')]
    public function test_invalid_server_modes_deny_both_authority_models(mixed $mode): void
    {
        config(['rsvp.mode' => $mode]);
        $this->get('/invite/'.str_repeat('a', 64))->assertNotFound();
        $this->get('/rsvp/'.str_repeat('b', 64))->assertStatus(503);
    }

    public static function invalidModes(): array
    {
        return [['PERSONAL'], ['true'], [''], [null], [false], ['unexpected'], ['legacy']];
    }

    public function test_personal_mode_without_schema_does_not_fall_back_to_legacy(): void
    {
        [$owner, $wedding, $group] = $this->fixture();
        $legacy = app(InvitationLink::class)->execute($wedding, $group, 'generate', 0);
        config(['rsvp.mode' => 'personal']);
        Schema::drop('guest_invitation_links');
        $this->get('/rsvp/'.str_repeat('c', 64))->assertStatus(503)->assertDontSee('SQLSTATE');
        $this->get($legacy['url'])->assertNotFound();
        $this->actingAs($owner)->getJson($this->base($wedding, $group))->assertStatus(503);
    }

    public function test_mode_cannot_be_selected_by_request_data_or_query(): void
    {
        [$owner, $wedding, $group, $guests] = $this->fixture();
        config(['rsvp.mode' => 'disabled']);
        $this->actingAs($owner)->postJson($this->base($wedding, $group).'/guests/'.$guests[0]->id.'/credential/generate?mode=personal', ['expectedVersion' => 0, 'expectedAccessVersion' => 0, 'personalRsvp' => true])->assertStatus(503);
        $this->assertDatabaseCount('guest_invitation_links', 0);
    }

    public function test_owner_status_has_no_secret_and_explicit_credential_lifecycle_returns_trusted_urls(): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        $status = $this->actingAs($owner)->getJson($this->base($wedding, $group))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $status->assertJsonPath('group.members.0.link_status', 'not_generated')->assertDontSee('token_hash')->assertDontSee('contact_email');
        config(['app.url' => 'https://approved.example.test']);
        $response = $this->credential($wedding, $group, $guests[0], 'generate')->assertOk();
        $url = $response->json('url');
        $this->assertMatchesRegularExpression('~^https://approved.example.test/rsvp/[a-f0-9]{64}$~', $url);
        $this->credential($wedding, $group, $guests[0], 'copy')->assertJsonPath('url', $url);
        $this->credential($wedding, $group, $guests[0], 'generate')->assertJsonPath('url', $url);
        $this->get($this->guestPage($wedding))->assertInertia(fn (Assert $page) => $page->missing('personalRsvp'))->assertDontSee(basename($url));
        $rotated = $this->credential($wedding, $group, $guests[0], 'regenerate')->assertOk()->json('url');
        $this->assertNotSame($url, $rotated);
        $this->get($url)->assertNotFound();
        $this->credential($wedding, $group, $guests[0], 'revoke')->assertOk()->assertJsonPath('url', null);
        $this->get($rotated)->assertNotFound();
        $this->assertDatabaseCount('guest_invitation_links', 1);
        $this->assertNull($guests[0]->fresh()->invitationLink->token_hash);
    }

    public function test_mutation_snapshots_support_consecutive_independent_credentials_and_enforce_stale_versions(): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        $this->actingAs($owner);
        $base = $this->base($wedding, $group);
        $snapshot = $this->getJson($base)->assertOk()->json('group');

        foreach ([0, 1, 3] as $index) {
            $member = collect($snapshot['members'])->firstWhere('guest.id', $guests[$index]->id);
            $response = $this->postJson($base.'/guests/'.$member['guest']['id'].'/credential/generate', [
                'expectedVersion' => $member['link_version'], 'expectedAccessVersion' => $member['access_version'],
            ])->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            $snapshot = $response->json('group');
            $updated = collect($snapshot['members'])->firstWhere('guest.id', $member['guest']['id']);
            $this->assertSame(1, $updated['link_version']);
            $this->assertSame($member['access_version'] + 1, $updated['access_version']);
            $this->assertSame('active', $updated['link_status']);
            $this->assertSame($this->getJson($base)->assertOk()->json('group'), $snapshot);
            $this->get($this->guestPage($wedding))->assertDontSee(basename($response->json('url')));
        }

        $juan = $snapshot['members'][0];
        $versions = ['expectedVersion' => $juan['link_version'], 'expectedAccessVersion' => $juan['access_version']];
        $this->postJson($base.'/guests/'.$guests[0]->id.'/credential/regenerate', $versions)->assertOk();
        $this->postJson($base.'/guests/'.$guests[0]->id.'/credential/copy', $versions)
            ->assertStatus(422)->assertJsonPath('kind', 'stale_access');
        $this->assertSame(1, $guests[1]->fresh()->invitationLink->version);
        $this->assertSame(1, $guests[3]->fresh()->invitationLink->version);
        $this->assertNull($guests[2]->fresh()->invitationLink);
    }

    public function test_untrusted_host_cannot_control_recovered_url_or_personal_metadata(): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        config(['app.url' => 'http://trusted.example.test']);
        $this->actingAs($owner)->withHeader('Host', 'hostile.example.test');
        $url = $this->credential($wedding, $group, $guests[0], 'generate')->assertOk()->json('url');
        $this->assertStringStartsWith('http://trusted.example.test/rsvp/', $url);
        $response = $this->get($url)->assertOk();
        $response->assertSee('http://trusted.example.test/w/')->assertDontSee('hostile.example.test');
    }

    public function test_invalid_trusted_url_rolls_back_issuance(): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        config(['app.url' => 'https://user@host.example.test']);
        $this->actingAs($owner);
        $this->credential($wedding, $group, $guests[0], 'generate')->assertStatus(503);
        $this->assertDatabaseCount('guest_invitation_links', 0);
    }

    public function test_cross_account_group_guest_and_viewer_operations_are_denied(): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        [$stranger, $foreignWedding, $foreignGroup, $foreignGuests] = $this->fixture();
        $this->actingAs($stranger)->getJson($this->base($wedding, $group))->assertForbidden();
        $this->credential($wedding, $group, $guests[0], 'generate')->assertForbidden();
        $this->actingAs($owner)->getJson($this->base($wedding, $foreignGroup))->assertNotFound();
        $this->credential($wedding, $group, $foreignGuests[0], 'generate')->assertNotFound();
        $this->getJson('/accounts/'.$wedding->account_id.'/weddings/'.$foreignWedding->id.'/invitation-groups/'.$foreignGroup->id.'/rsvp-access')->assertNotFound();
        $viewer = User::factory()->create();
        $wedding->account->users()->attach($viewer, ['role' => 'viewer']);
        $this->actingAs($viewer)->getJson($this->base($wedding, $group))->assertForbidden();
        $this->assertDatabaseCount('guest_invitation_links', 0);
    }

    public function test_stale_and_unknown_owner_payloads_fail_without_changes(): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        $this->actingAs($owner);
        $this->credential($wedding, $group, $guests[0], 'generate')->assertOk();
        $this->postJson($this->base($wedding, $group).'/guests/'.$guests[0]->id.'/credential/revoke', ['expectedVersion' => 0, 'expectedAccessVersion' => 0])->assertStatus(422)->assertJsonPath('kind', 'stale_access');
        $this->credential($wedding, $group, $guests[0], 'copy', ['wedding_id' => 42])->assertStatus(422)->assertJsonPath('kind', 'validation');
        $this->assertSame('active', app(PersonalRsvpTransaction::class)->status($guests[0]->fresh()));
    }

    public function test_same_group_delegation_rotation_and_revocation_preserve_answers(): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        $this->actingAs($owner);
        $juanUrl = $this->credential($wedding, $group, $guests[0], 'generate')->json('url');
        $miguelUrl = $this->credential($wedding, $group, $guests[2], 'generate')->json('url');
        RsvpResponse::factory()->for($guests[2])->create(['status' => 'attending']);
        $timestamp = $guests[2]->rsvpResponse->responded_at->toIso8601String();
        $this->delegate($wedding, $group, $guests[2], $guests[0])->assertOk();
        $this->get($miguelUrl)->assertNotFound();
        $this->get($juanUrl)->assertOk()->assertSee('Miguel');
        $new = $this->credential($wedding, $group, $guests[0], 'regenerate')->json('url');
        $this->get($new)->assertOk()->assertSee('Miguel');
        $this->credential($wedding, $group, $guests[0], 'revoke')->assertOk();
        $this->assertNull($guests[2]->fresh()->rsvpDelegation->representative_guest_id);
        $this->assertSame($timestamp, $guests[2]->fresh()->rsvpResponse->responded_at->toIso8601String());
        $this->assertSame('attending', $guests[2]->fresh()->rsvpResponse->status);
    }

    public function test_reassignment_and_removal_change_existing_links_immediately(): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        $this->actingAs($owner);
        $juan = $this->credential($wedding, $group, $guests[0], 'generate')->json('url');
        $maria = $this->credential($wedding, $group, $guests[1], 'generate')->json('url');
        $this->delegate($wedding, $group, $guests[2], $guests[0])->assertOk();
        $old = $this->getJson($juan)->json('rsvp');
        $this->delegate($wedding, $group, $guests[2], $guests[1])->assertOk();
        $this->postJson($juan.'/responses', $this->payload($old, [1 => 'attending']))->assertStatus(422)->assertJsonPath('kind', 'stale_access');
        $this->get($maria)->assertOk()->assertSee('Miguel')->assertDontSee('Andrea');
        $this->delegate($wedding, $group, $guests[2], null)->assertOk();
        $this->get($maria)->assertOk()->assertDontSee('Miguel');
        $this->assertDatabaseCount('rsvp_responses', 0);
    }

    public function test_delegation_rejects_self_foreign_and_represented_credential_requests(): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        [, , $other, $foreign] = $this->fixture();
        $this->actingAs($owner);
        $this->credential($wedding, $group, $guests[0], 'generate');
        $this->delegate($wedding, $group, $guests[0], $guests[0])->assertStatus(422);
        $this->postJson($this->base($wedding, $group).'/guests/'.$guests[1]->id.'/delegation', ['representativeId' => $foreign[0]->id, 'expectedAccessVersions' => [$guests[1]->id => 0], 'expectedDependentLinkVersion' => 0])->assertNotFound();
        $this->delegate($wedding, $group, $guests[2], $guests[0])->assertOk();
        $this->credential($wedding, $group, $guests[2], 'generate')->assertStatus(422);
        $this->delegate($wedding, $group, $guests[0], $guests[2])->assertStatus(422);
        $this->assertDatabaseCount('rsvp_delegations', 1);
    }

    public function test_personal_get_is_stateless_scoped_and_private_in_html_and_json(): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        $this->actingAs($owner);
        $url = $this->credential($wedding, $group, $guests[0], 'generate')->json('url');
        $this->app['auth']->forgetGuards();
        $response = $this->get($url)->assertOk()->assertSee("You're invited, Juan!", false)->assertDontSee('Maria')->assertDontSee('Andrea')->assertDontSee('Miguel')->assertDontSee('secret-contact@example.test')->assertDontSee(basename($url));
        $this->assertCount(0, $response->headers->getCookies());
        foreach (InvitationLink::HEADERS as $key => $value) {
            $this->assertSame($key === 'Cache-Control' ? 'no-store, private' : $value, $response->headers->get($key));
        }
        $json = $this->getJson($url)->assertOk()->json('rsvp');
        $this->assertSame(['greeting', 'access_version', 'guests'], array_keys($json));
        $this->assertCount(1, $json['guests']);
        $this->assertSame(['reference', 'name', 'version', 'represented', 'status', 'respondedAt'], array_keys($json['guests'][0]));
        $this->assertSame('pending', $json['guests'][0]['status']);
        $this->get('/w/'.$wedding->public_slug)->assertOk()->assertDontSee('Juan')->assertDontSee('Your RSVP');
    }

    public function test_partial_updates_noops_and_independent_baselines_preserve_counts_and_timestamps(): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        $this->actingAs($owner);
        $juan = $this->credential($wedding, $group, $guests[0], 'generate')->json('url');
        $maria = $this->credential($wedding, $group, $guests[1], 'generate')->json('url');
        $this->delegate($wedding, $group, $guests[2], $guests[0]);
        $j = $this->getJson($juan)->json('rsvp');
        $m = $this->getJson($maria)->json('rsvp');
        $payload = $this->payload($j, [0 => 'attending']);
        $saved = $this->postJson($juan.'/responses', $payload)->assertOk()->json('rsvp');
        $timestamp = $saved['guests'][0]['respondedAt'];
        $this->travel(1)->minutes();
        $this->postJson($juan.'/responses', $payload)->assertOk()->assertJsonPath('rsvp.guests.0.respondedAt', $timestamp)->assertJsonPath('rsvp.guests.0.version', 1);
        $this->postJson($maria.'/responses', $this->payload($m, [0 => 'declined']))->assertOk();
        $this->getJson($juan)->assertJsonPath('rsvp.guests.1.status', 'pending');
        $this->postJson($juan.'/responses', $this->payload($saved, [0 => 'declined', 1 => 'attending']))->assertOk();
        $this->assertSame(['invited' => 4, 'attending' => 1, 'declined' => 2, 'pending' => 1], app(WeddingAttendance::class)->summary($wedding));
    }

    public function test_stale_or_foreign_targets_roll_back_the_entire_batch(): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        $this->actingAs($owner);
        $url = $this->credential($wedding, $group, $guests[0], 'generate')->json('url');
        $this->delegate($wedding, $group, $guests[2], $guests[0]);
        $state = $this->getJson($url)->json('rsvp');
        $this->postJson($url.'/responses', $this->payload($state, [0 => 'attending']))->assertOk();
        $this->postJson($url.'/responses', $this->payload($state, [0 => 'declined', 1 => 'attending']))->assertStatus(422)->assertJsonPath('kind', 'stale_response');
        $payload = $this->payload($this->getJson($url)->json('rsvp'), [1 => 'attending']);
        $payload['responses'][] = ['guest' => hash_hmac('sha256', 'personal-rsvp:'.$guests[1]->id, basename($url)), 'version' => 0, 'status' => 'attending'];
        $this->postJson($url.'/responses', $payload)->assertStatus(422);
        $this->assertDatabaseCount('rsvp_responses', 1);
        $this->assertDatabaseHas('rsvp_responses', ['guest_id' => $guests[0]->id, 'status' => 'attending']);
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_guest_payloads_return_fixed_private_errors(array $mutation): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        $this->actingAs($owner);
        $url = $this->credential($wedding, $group, $guests[0], 'generate')->json('url');
        $state = $this->getJson($url)->json('rsvp');
        $payload = array_replace_recursive($this->payload($state, [0 => 'attending']), $mutation);
        $this->postJson($url.'/responses', $payload)->assertStatus(422)->assertJsonPath('kind', 'validation')->assertDontSee('evil-value');
        $this->assertDatabaseCount('rsvp_responses', 0);
    }

    public static function invalidPayloads(): array
    {
        return [
            [['responses' => [['status' => 'evil-value']]]],
            [['responses' => [['guest' => 1]]]],
            [['responses' => [['version' => -1]]]],
            [['responses' => [['owner_id' => 1]]]],
            [['wedding_id' => 1]],
        ];
    }

    public function test_personal_mode_denies_all_legacy_surfaces_without_changing_stored_credentials(): void
    {
        [$owner, $wedding, $group, $guests] = $this->fixture();
        $legacy = app(InvitationLink::class)->execute($wedding, $group, 'generate', 0);
        $before = $group->fresh()->getRawOriginal('invitation_token');
        config(['rsvp.mode' => 'personal']);
        $this->actingAs($owner);
        $this->get($legacy['url'])->assertNotFound();
        $this->postJson($legacy['url'].'/rsvp', ['version' => 0, 'responses' => []])->assertNotFound();
        foreach (['generate', 'copy', 'regenerate', 'revoke'] as $action) {
            $this->postJson('/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/invitation-groups/'.$group->id.'/invitation-link/'.$action, ['link_version' => 1])->assertNotFound();
        }
        $this->get('/rsvp/'.basename($legacy['url']))->assertNotFound();
        $this->assertSame($before, $group->fresh()->getRawOriginal('invitation_token'));
        $this->assertDatabaseCount('guest_invitation_links', 0);
    }

    public function test_rename_updates_only_target_version_additions_do_not_invalidate_other_guests(): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        $this->actingAs($owner);
        $juan = $this->credential($wedding, $group, $guests[0], 'generate')->json('url');
        $maria = $this->credential($wedding, $group, $guests[1], 'generate')->json('url');
        $j = $this->getJson($juan)->json('rsvp');
        $m = $this->getJson($maria)->json('rsvp');
        $this->patch($this->guestRoute($wedding, $group, $guests[0]), ['name' => 'Juan renamed'])->assertRedirect();
        $this->assertSame(1, $guests[0]->fresh()->rsvp_version);
        $partial = Guest::query()->select('id', 'name')->findOrFail($guests[0]->id);
        try {
            $partial->update(['name' => 'Incomplete version overwrite']);
            $this->fail('A partial guest model must not reset its version.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('guest', $error->errors());
        }
        $this->assertSame('Juan renamed', $guests[0]->fresh()->name);
        $this->assertSame(0, $guests[1]->fresh()->rsvp_version);
        $this->postJson($juan.'/responses', $this->payload($j, [0 => 'attending']))->assertStatus(422)->assertJsonPath('kind', 'stale_response');
        $this->post('/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/invitation-groups/'.$group->id.'/guests', ['name' => 'New pending'])->assertRedirect();
        $this->postJson($maria.'/responses', $this->payload($m, [0 => 'attending']))->assertOk();
        $this->assertSame(0, $group->guests()->latest('id')->first()->rsvp_version);
        $this->patch($this->guestRoute($wedding, $group, $guests[0]), ['name' => 'Juan renamed'])->assertRedirect();
        $this->assertSame(1, $guests[0]->fresh()->rsvp_version);
    }

    public function test_retention_guards_prevent_personal_parent_and_guest_deletion_after_revocation(): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        $this->actingAs($owner);
        $this->credential($wedding, $group, $guests[0], 'generate');
        $this->delegate($wedding, $group, $guests[2], $guests[0]);
        $this->delegate($wedding, $group, $guests[2], null);
        $this->credential($wedding, $group, $guests[0], 'revoke');
        $this->delete($this->guestRoute($wedding, $group, $guests[0]))->assertSessionHasErrors('guest');
        $this->delete($this->guestRoute($wedding, $group, $guests[2]))->assertSessionHasErrors('guest');
        $this->delete('/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/invitation-groups/'.$group->id)->assertSessionHasErrors('invitation_link');
        $this->assertDatabaseCount('guests', 4);
        $this->delete($this->guestRoute($wedding, $group, $guests[3]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('guests', 3);
        try {
            $wedding->delete();
            $this->fail('A parent wedding must retain issued guest records.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('wedding', $error->errors());
        }
        $this->assertDatabaseHas('weddings', ['id' => $wedding->id]);
    }

    public function test_unpublished_unsupported_and_guessed_credentials_are_generic_private_404(): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        $this->actingAs($owner);
        $url = $this->credential($wedding, $group, $guests[0], 'generate')->json('url');
        $this->get('/rsvp/short')->assertNotFound()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->getJson('/rsvp/'.str_repeat('f', 64))->assertNotFound();
        $wedding->update(['partner_one_name' => 'PRIVATE DRAFT NAME']);
        $this->get($url)->assertOk()->assertDontSee('PRIVATE DRAFT NAME')->assertSee('Published Alex');
        DB::table('website_revisions')->where('id', $wedding->published_revision_id)->update(['schema_version' => 999]);
        $this->get($url)->assertNotFound()->assertDontSee('SQLSTATE');
        $wedding->forceFill(['published_revision_id' => null])->save();
        $this->postJson($url.'/responses', [])->assertNotFound();
    }

    public function test_csrf_bootstrap_is_independent_and_real_throttles_isolate_personal_credentials(): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        $this->actingAs($owner);
        $juan = $this->credential($wedding, $group, $guests[0], 'generate')->json('url');
        $maria = $this->credential($wedding, $group, $guests[1], 'generate')->json('url');
        $j = $this->getJson($juan)->json('rsvp');
        $m = $this->getJson($maria)->json('rsvp');
        for ($index = 0; $index < 20; $index++) {
            $this->getJson('/invitation-rsvp/csrf-token')->assertOk();
            $this->postJson($juan.'/responses', $this->payload($j, [0 => 'attending']))->assertOk();
        }
        $limited = $this->postJson($juan.'/responses', $this->payload($j, [0 => 'attending']))->assertStatus(429)->assertJsonPath('kind', 'rate_limited');
        $this->assertGreaterThan(0, (int) $limited->headers->get('Retry-After'));
        $this->postJson($maria.'/responses', $this->payload($m, [0 => 'declined']))->assertOk();
        $this->getJson('/invitation-rsvp/csrf-token')->assertOk();
    }

    public function test_submission_and_owner_recovery_retain_actual_csrf_middleware(): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        $this->actingAs($owner);
        $url = $this->credential($wedding, $group, $guests[0], 'generate')->json('url');
        $state = $this->getJson($url)->json('rsvp');
        $app = $this->app;
        $this->app->bind(ValidateCsrfToken::class, fn () => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $this->postJson($url.'/responses', $this->payload($state, [0 => 'attending']))->assertStatus(419)->assertJsonPath('kind', 'session');
        $this->credential($wedding, $group, $guests[0], 'copy')->assertStatus(419);
        $this->withSession(['_token' => 'synthetic-csrf-token'])->withHeader('X-CSRF-TOKEN', 'synthetic-csrf-token')
            ->postJson($url.'/responses', $this->payload($state, [0 => 'attending']))->assertOk();
    }

    public function test_application_redaction_covers_personal_paths_and_nested_token_values(): void
    {
        $token = str_repeat('c', 64);
        $this->assertStringNotContainsString($token, RedactInvitationTokens::text('https://example.test/rsvp/'.$token.'/responses'));
        $this->assertStringNotContainsString('guessed-short', RedactInvitationTokens::text('/rsvp/guessed-short?query=x'));
        $stream = fopen('php://memory', 'w+');
        $monolog = new Logger('synthetic-personal');
        $monolog->pushHandler(new StreamHandler($stream));
        $logger = new \Illuminate\Log\Logger($monolog);
        (new RedactInvitationTokens)($logger);
        $logger->warning('/rsvp/'.$token, ['nested' => ['link' => 'https://example.test/rsvp/'.$token, $token => new \RuntimeException('/rsvp/'.$token)]]);
        rewind($stream);
        $logged = stream_get_contents($stream);
        $this->assertStringNotContainsString($token, $logged);
        $this->assertStringContainsString('[redacted]', $logged);
        fclose($stream);
    }

    public function test_anonymous_management_and_corrupt_recovery_fail_without_sensitive_response_data(): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        $this->getJson($this->base($wedding, $group))->assertUnauthorized()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($owner);
        $url = $this->credential($wedding, $group, $guests[0], 'generate')->json('url');
        DB::table('guest_invitation_links')->where('guest_id', $guests[0]->id)->update(['token' => 'invalid-encrypted-fixture']);
        $this->credential($wedding, $group, $guests[0], 'copy')->assertStatus(422)->assertDontSee('invalid-encrypted-fixture')->assertDontSee(basename($url));
        $this->credential($wedding, $group, $guests[0], 'regenerate')->assertOk();
        $this->get($url)->assertNotFound();
    }

    public function test_empty_group_rules_and_parent_response_retention_have_friendly_errors(): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        $this->actingAs($owner);
        foreach (array_slice($guests, 1) as $guest) {
            $this->delete($this->guestRoute($wedding, $group, $guest))->assertSessionHasNoErrors();
        }
        $this->delete($this->guestRoute($wedding, $group, $guests[0]))->assertSessionHasErrors('guest');
        $this->delete('/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/invitation-groups/'.$group->id)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('guests', 0);
        $other = InvitationGroup::factory()->for($wedding)->withGuests(1)->create();
        RsvpResponse::factory()->for($other->guests->first())->create();
        $this->delete('/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/invitation-groups/'.$other->id)->assertSessionHasErrors('invitation_link');
        $this->assertDatabaseCount('rsvp_responses', 1);
    }

    public function test_manipulated_membership_and_duplicate_responses_cannot_change_data(): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        $this->actingAs($owner);
        $this->patchJson($this->guestRoute($wedding, $group, $guests[0]), ['name' => 'Attempted rename', 'invitation_group_id' => 999])->assertStatus(422);
        $this->assertSame('Juan', $guests[0]->fresh()->name);
        $url = $this->credential($wedding, $group, $guests[0], 'generate')->json('url');
        $payload = $this->payload($this->getJson($url)->json('rsvp'), [0 => 'attending']);
        $payload['responses'][] = $payload['responses'][0];
        $this->postJson($url.'/responses', $payload)->assertStatus(422)->assertJsonPath('kind', 'validation');
        $this->assertDatabaseCount('rsvp_responses', 0);
        [, , , $foreign] = $this->fixture();
        $payload['responses'] = [['guest' => hash_hmac('sha256', 'personal-rsvp:'.$foreign[0]->id, basename($url)), 'version' => 0, 'status' => 'attending']];
        $this->postJson($url.'/responses', $payload)->assertStatus(422);
        $this->assertDatabaseCount('rsvp_responses', 0);
    }

    public function test_personal_page_republishes_facts_and_photo_without_changing_credentials_or_answers(): void
    {
        Storage::fake('wedding_photos');
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        $this->actingAs($owner);
        $url = $this->credential($wedding, $group, $guests[0], 'generate')->json('url');
        $state = $this->getJson($url)->json('rsvp');
        $this->postJson($url.'/responses', $this->payload($state, [0 => 'attending']))->assertOk();
        $this->post(route('accounts.weddings.invitation.update', [$wedding->account_id, $wedding]), [
            '_method' => 'put', 'content' => json_encode(WebsiteDraft::defaultContent()), 'draft_version' => 0,
            'hero_photo_action' => 'replace', 'hero_photo' => UploadedFile::fake()->image('synthetic.png'),
        ], ['Content-Type' => 'multipart/form-data'])->assertSessionHasNoErrors();
        $path = $wedding->websiteDraft()->sole()->hero_photo_path;
        $photoUrl = route('public.weddings.photo', [$wedding->public_slug, pathinfo($path, PATHINFO_FILENAME)]);
        $this->get($photoUrl)->assertNotFound();
        $wedding->update(['partner_one_name' => 'Republished Alex']);
        $this->get($url)->assertDontSee('Republished Alex');
        $publisher = app(WeddingPublication::class);
        $summary = $publisher->summary($wedding->fresh());
        $publisher->publish($wedding->fresh(), ['draft_version' => $summary['draftVersion'], 'publication_version' => $summary['publicationVersion'], 'snapshot_token' => $summary['snapshotToken']]);
        $this->get($url)->assertOk()->assertSee('Republished Alex')->assertSee('invitation-hero-photo', false);
        $this->get($photoUrl)->assertOk();
        $this->assertSame(1, $guests[0]->fresh()->invitationLink->version);
        $this->getJson($url)->assertJsonPath('rsvp.guests.0.status', 'attending')->assertJsonPath('rsvp.guests.0.version', 1);
    }

    public function test_personal_fallback_html_escapes_names_and_keeps_them_out_of_metadata(): void
    {
        [$owner, $wedding, $group, $guests] = $this->personalFixture();
        $name = '<script>alert(1)</script>';
        $guests[0]->update(['name' => $name]);
        $this->actingAs($owner);
        $url = $this->credential($wedding, $group, $guests[0], 'generate')->json('url');
        $response = $this->get($url)->assertOk()->assertSee(e($name), false)->assertDontSee($name, false);
        preg_match('~<head>(.*?)</head>~s', $response->getContent(), $head);
        $this->assertStringNotContainsString('alert(1)', $head[1]);
    }

    private function fixture(): array
    {
        $owner = User::factory()->create();
        $account = Account::create(['name' => 'Synthetic HTTP workspace']);
        $account->users()->attach($owner, ['role' => 'owner']);
        $wedding = $account->weddings()->create(['partner_one_name' => 'Published Alex', 'partner_two_name' => 'Published Jamie', 'wedding_date' => '2027-05-12', 'public_slug' => fake()->uuid()]);
        $wedding->websiteDraft()->create(['schema_version' => 1, 'lock_version' => 0, 'content' => WebsiteDraft::defaultContent()]);
        $publisher = app(WeddingPublication::class);
        $publisher->publish($wedding, ['draft_version' => 0, 'publication_version' => 0, 'snapshot_token' => $publisher->summary($wedding)['snapshotToken']]);
        $group = InvitationGroup::factory()->for($wedding)->create(['contact_email' => 'secret-contact@example.test']);
        $guests = [];
        foreach (['Juan', 'Maria', 'Miguel', 'Andrea'] as $name) {
            $guests[] = $group->guests()->create(['name' => $name])->fresh();
        }

        return [$owner, $wedding->fresh(), $group, $guests];
    }

    private function personalFixture(): array
    {
        config(['rsvp.mode' => 'personal']);

        return $this->fixture();
    }

    private function base(Wedding $wedding, InvitationGroup $group): string
    {
        return '/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/invitation-groups/'.$group->id.'/rsvp-access';
    }

    private function guestPage(Wedding $wedding): string
    {
        return '/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/guests';
    }

    private function guestRoute(Wedding $wedding, InvitationGroup $group, Guest $guest): string
    {
        return '/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/invitation-groups/'.$group->id.'/guests/'.$guest->id;
    }

    private function credential(Wedding $wedding, InvitationGroup $group, Guest $guest, string $action, array $extra = []): TestResponse
    {
        $fresh = $guest->fresh();

        return $this->postJson($this->base($wedding, $group).'/guests/'.$guest->id.'/credential/'.$action,
            ['expectedVersion' => $fresh->invitationLink?->version ?? 0, 'expectedAccessVersion' => $fresh->rsvp_access_version, ...$extra]);
    }

    private function delegate(Wedding $wedding, InvitationGroup $group, Guest $guest, ?Guest $representative): TestResponse
    {
        $fresh = $guest->fresh();
        $ids = array_unique(array_filter([$guest->id, $representative?->id, $fresh->rsvpDelegation?->representative_guest_id]));
        $versions = Guest::query()->whereIn('id', $ids)->pluck('rsvp_access_version', 'id')->all();

        return $this->postJson($this->base($wedding, $group).'/guests/'.$guest->id.'/delegation', ['representativeId' => $representative?->id, 'expectedAccessVersions' => $versions, 'expectedDependentLinkVersion' => $fresh->invitationLink?->version ?? 0]);
    }

    private function payload(array $state, array $choices): array
    {
        $responses = [];
        foreach ($choices as $index => $status) {
            $target = $state['guests'][$index];
            $responses[] = ['guest' => $target['reference'], 'version' => $target['version'], 'status' => $status];
        }

        return ['access_version' => $state['access_version'], 'responses' => $responses];
    }
}
