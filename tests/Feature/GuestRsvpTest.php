<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\InvitationGroup;
use App\Models\RsvpResponse;
use App\Models\User;
use App\Models\WebsiteDraft;
use App\Models\Wedding;
use App\Services\InvitationLink;
use App\Services\InvitationRsvp;
use App\Services\WeddingPublication;
use Illuminate\Cache\ArrayStore;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GuestRsvpTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_runtime_keeps_rate_limit_state_out_of_the_development_file_cache(): void
    {
        $this->assertInstanceOf(ArrayStore::class, app('cache')->store()->getStore());
    }

    public function test_initial_pending_state_is_private_and_only_on_personalized_pages(): void
    {
        [$owner, $wedding, $group, $url] = $this->fixture();
        $response = $this->get($url)->assertOk()->assertViewHas('rsvp')
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
        $state = $response->viewData('rsvp');
        $this->assertSame(0, $state['version']);
        $this->assertSame(['pending', 'pending', 'pending'], array_column($state['guests'], 'status'));
        $this->assertSame([null, null, null], array_column($state['guests'], 'respondedAt'));
        $this->assertSame(['reference', 'name', 'status', 'respondedAt'], array_keys($state['guests'][0]));
        foreach ([$group->contact_email, $group->contact_phone, basename($url), 'guest_id', 'account_id', 'invitation_token'] as $private) {
            $response->assertDontSee($private, false);
        }
        $this->assertCount(0, $response->headers->getCookies());
        $this->get(route('public.weddings.show', $wedding->public_slug))->assertOk()->assertDontSee('id="invitation-rsvp"', false)->assertDontSee('id="rsvp-data"', false);
        $this->assertDatabaseCount('rsvp_responses', 0);
    }

    public static function statuses(): array
    {
        return ['attending' => ['attending'], 'declining' => ['declined']];
    }

    #[DataProvider('statuses')]
    public function test_single_response_persists_with_a_timestamp_and_omitted_people_stay_pending(string $status): void
    {
        [, , $group, $url] = $this->fixture();
        $this->travelTo('2026-10-16 12:00:00');
        $state = $this->state($url);
        $response = $this->postJson($url.'/rsvp', $this->payload($state, [0 => $status]))->assertOk()->assertJsonPath('rsvp.version', 1);
        $this->assertSame($status, $response->json('rsvp.guests.0.status'));
        $guest = $group->guests()->orderBy('id')->first();
        $this->assertDatabaseHas('rsvp_responses', ['guest_id' => $guest->id, 'status' => $status, 'responded_at' => '2026-10-16 12:00:00']);
        $this->assertDatabaseCount('rsvp_responses', 1);
        $reloaded = $this->state($url);
        $this->assertSame([$status, 'pending', 'pending'], array_column($reloaded['guests'], 'status'));
        $this->assertNotNull($reloaded['guests'][0]['respondedAt']);
    }

    public function test_multiple_responses_and_later_partial_updates_preserve_omitted_answers(): void
    {
        [, , , $url] = $this->fixture();
        $state = $this->state($url);
        $this->postJson($url.'/rsvp', $this->payload($state, [0 => 'attending', 1 => 'declined']))->assertOk();
        $next = $this->state($url);
        $this->postJson($url.'/rsvp', $this->payload($next, [0 => 'declined']))->assertOk()->assertJsonPath('rsvp.version', 2);
        $this->assertSame(['declined', 'declined', 'pending'], array_column($this->state($url)['guests'], 'status'));
        $this->assertDatabaseCount('rsvp_responses', 2);
    }

    public function test_repeating_an_identical_submission_is_a_no_op_even_after_a_lost_response(): void
    {
        [, , $group, $url] = $this->fixture();
        $payload = $this->payload($this->state($url), [0 => 'attending']);
        $this->postJson($url.'/rsvp', $payload)->assertOk();
        $before = RsvpResponse::query()->sole()->getAttributes();
        $this->travel(2)->hours();
        $this->postJson($url.'/rsvp', $payload)->assertOk()->assertJsonPath('rsvp.version', 1);
        $this->assertSame($before, RsvpResponse::query()->sole()->getAttributes());
        $this->assertSame(1, $group->fresh()->rsvp_version);
    }

    public function test_stale_and_future_versions_cannot_overwrite_saved_or_omitted_answers(): void
    {
        [, , , $url] = $this->fixture();
        $state = $this->state($url);
        $this->postJson($url.'/rsvp', $this->payload($state, [0 => 'attending']))->assertOk();
        $this->postJson($url.'/rsvp', $this->payload($state, [0 => 'declined', 1 => 'attending']))->assertUnprocessable()->assertJsonPath('message', 'These responses or invited names changed in another window. Reload this invitation and review the saved choices before trying again.');
        $future = $this->payload($this->state($url), [0 => 'attending']);
        $future['version'] = 99;
        $this->postJson($url.'/rsvp', $future)->assertUnprocessable();
        $this->assertSame(['attending', 'pending', 'pending'], array_column($this->state($url)['guests'], 'status'));
        $this->assertDatabaseCount('rsvp_responses', 1);
    }

    public static function invalidInputs(): array
    {
        return [
            'no fields' => [[]], 'no responses' => [['version' => 0, 'responses' => []]],
            'unsupported state' => [['version' => 0, 'responses' => [['guest' => 'REF', 'status' => 'maybe']]]],
            'pending is absence' => [['version' => 0, 'responses' => [['guest' => 'REF', 'status' => 'pending']]]],
            'duplicate person' => [['version' => 0, 'responses' => [['guest' => 'REF', 'status' => 'attending'], ['guest' => 'REF', 'status' => 'declined']]]],
            'numeric identifier' => [['version' => 0, 'responses' => [['guest' => 1, 'status' => 'attending']]]],
            'missing choice' => [['version' => 0, 'responses' => [['guest' => 'REF']]]],
            'negative version' => [['version' => -1, 'responses' => [['guest' => 'REF', 'status' => 'attending']]]],
            'missing version' => [['responses' => [['guest' => 'REF', 'status' => 'attending']]]],
            'overflow version' => [['version' => '4294967296', 'responses' => [['guest' => 'REF', 'status' => 'attending']]]],
            'nested ownership' => [['version' => 0, 'responses' => [['guest' => 'REF', 'status' => 'attending', 'wedding_id' => 1]]]],
            'ownership field' => [['version' => 0, 'responses' => [['guest' => 'REF', 'status' => 'attending']], 'account_id' => 1]],
            'arbitrary input key' => [['version' => 0, 'responses' => [['guest' => 'REF', 'status' => 'attending']], 'PRIVATE_SECRET' => 1]],
            'not a list' => [['version' => 0, 'responses' => ['PRIVATE_SECRET' => ['guest' => 'REF', 'status' => 'attending']]]],
            'quantity limit' => [['version' => 0, 'responses' => array_fill(0, 101, ['guest' => 'REF', 'status' => 'attending'])]],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_inputs_fail_without_data_loss_or_reflected_input(array $input): void
    {
        [, , $group, $url] = $this->fixture();
        $reference = $this->state($url)['guests'][0]['reference'];
        $input = json_decode(str_replace('REF', $reference, json_encode($input)), true);
        $this->postJson($url.'/rsvp', $input)->assertUnprocessable()->assertJsonPath('message', 'Choose Attending or Declining for at least one invited person. Use only the choices shown in this invitation. Your saved responses have not changed.')->assertDontSee('PRIVATE_SECRET')->assertDontSee($reference);
        $this->assertDatabaseCount('rsvp_responses', 0);
        $this->assertSame(0, $group->fresh()->rsvp_version);
    }

    public function test_unknown_and_cross_group_or_wedding_references_roll_back_the_entire_submission(): void
    {
        [, $wedding, $group, $url] = $this->fixture();
        $other = InvitationGroup::factory()->for($wedding)->withGuests()->create();
        $otherUrl = app(InvitationLink::class)->execute($wedding, $other, 'generate', 0)['url'];
        [, , , $foreignUrl] = $this->fixture();
        $state = $this->state($url);
        foreach ([str_repeat('0', 64), $this->state($otherUrl)['guests'][0]['reference'], $this->state($foreignUrl)['guests'][0]['reference']] as $unknown) {
            $payload = $this->payload($state, [0 => 'attending']);
            $payload['responses'][] = ['guest' => $unknown, 'status' => 'declined'];
            $this->postJson($url.'/rsvp', $payload)->assertUnprocessable()->assertDontSee($unknown);
            $this->assertDatabaseCount('rsvp_responses', 0);
        }
        $this->assertSame(0, $group->fresh()->rsvp_version);
    }

    public function test_invalid_revoked_rotated_and_unpublished_links_cannot_read_or_submit(): void
    {
        [, $wedding, $group, $url] = $this->fixture();
        $payload = $this->payload($this->state($url), [0 => 'attending']);
        $links = app(InvitationLink::class);
        $rotated = $links->execute($wedding, $group, 'regenerate', 1)['url'];
        $this->postJson($url.'/rsvp', $payload)->assertNotFound()->assertExactJson(['message' => 'This invitation is unavailable.']);
        $this->get($url)->assertNotFound();
        $links->execute($wedding, $group, 'revoke', 2);
        $this->postJson($rotated.'/rsvp', $payload)->assertNotFound();
        $this->postJson('/invite/'.str_repeat('0', 64).'/rsvp', $payload)->assertNotFound();
        $active = $links->execute($wedding, $group, 'regenerate', 3)['url'];
        app(WeddingPublication::class)->unpublish($wedding->fresh(), 1);
        $this->get($active)->assertNotFound();
        $this->postJson($active.'/rsvp', $payload)->assertNotFound();
        $this->assertDatabaseCount('rsvp_responses', 0);
    }

    public function test_token_and_publication_are_revalidated_after_request_authorization(): void
    {
        [, $wedding, $group, $url] = $this->fixture();
        $payload = $this->payload($this->state($url), [0 => 'attending']);
        app(InvitationRsvp::class)->assertAvailable(basename($url));
        app(InvitationLink::class)->execute($wedding, $group, 'revoke', 1);
        try {
            app(InvitationRsvp::class)->submit(basename($url), $payload);
            $this->fail('A token revoked after authorization must be rejected inside the operation.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseCount('rsvp_responses', 0);
        }
    }

    public function test_unpublication_after_authorization_is_rechecked_by_the_submission_transaction(): void
    {
        [, $wedding, , $url] = $this->fixture();
        $payload = $this->payload($this->state($url), [0 => 'attending']);
        app(InvitationRsvp::class)->assertAvailable(basename($url));
        app(WeddingPublication::class)->unpublish($wedding->fresh(), 1);
        try {
            app(InvitationRsvp::class)->submit(basename($url), $payload);
            $this->fail('An unpublished wedding must be rejected inside the operation.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
            $this->assertDatabaseCount('rsvp_responses', 0);
        }
    }

    public function test_signing_in_to_another_account_does_not_replace_the_required_bearer_credential(): void
    {
        [, , , $url] = $this->fixture();
        $payload = $this->payload($this->state($url), [0 => 'attending']);
        $this->actingAs(User::factory()->create());
        $this->postJson('/invite/'.str_repeat('0', 64).'/rsvp', $payload)->assertNotFound();
        $this->postJson('/rsvp', $payload)->assertNotFound();
        $this->assertDatabaseCount('rsvp_responses', 0);
        $this->postJson($url.'/rsvp', $payload)->assertOk();
        $this->assertDatabaseCount('rsvp_responses', 1);
    }

    public function test_atomic_failure_preserves_previously_saved_choices_and_timestamps(): void
    {
        [, , $group, $url] = $this->fixture();
        $state = $this->state($url);
        $this->postJson($url.'/rsvp', $this->payload($state, [0 => 'attending']))->assertOk();
        $before = RsvpResponse::query()->sole()->getAttributes();
        RsvpResponse::creating(function (): void {
            throw new RuntimeException('Simulated later insert failure');
        });
        try {
            $this->postJson($url.'/rsvp', $this->payload($this->state($url), [0 => 'declined', 1 => 'attending']))->assertServerError();
            $this->assertSame($before, RsvpResponse::query()->sole()->getAttributes());
            $this->assertSame(1, $group->fresh()->rsvp_version);
        } finally {
            RsvpResponse::flushEventListeners();
        }
    }

    public function test_transaction_failure_does_not_partially_save_a_group_or_advance_its_version(): void
    {
        [, , $group, $url] = $this->fixture();
        $input = $this->payload($this->state($url), [0 => 'attending', 1 => 'declined']);
        $created = 0;
        RsvpResponse::creating(function () use (&$created): void {
            if (++$created === 2) {
                throw new RuntimeException('Simulated response storage failure');
            }
        });
        try {
            $this->postJson($url.'/rsvp', $input)->assertServerError()->assertDontSee(basename($url));
            $this->assertDatabaseCount('rsvp_responses', 0);
            $this->assertSame(0, $group->fresh()->rsvp_version);
        } finally {
            RsvpResponse::flushEventListeners();
        }
    }

    public function test_renaming_preserves_responses_adding_starts_pending_and_responded_guests_cannot_be_removed(): void
    {
        [$owner, $wedding, $group, $url] = $this->fixture();
        $state = $this->state($url);
        $this->postJson($url.'/rsvp', $this->payload($state, [0 => 'attending']))->assertOk();
        $guest = $group->guests()->orderBy('id')->first();
        $base = '/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/invitation-groups/'.$group->id;
        $before = $guest->rsvpResponse->getAttributes();
        $this->actingAs($owner)->patch($base.'/guests/'.$guest->id, ['name' => 'Renamed person'])->assertRedirect();
        $this->assertSame($before, $guest->fresh()->rsvpResponse->getAttributes());
        $this->post($base.'/guests', ['name' => 'New pending person'])->assertRedirect();
        $this->delete($base.'/guests/'.$guest->id)->assertSessionHasErrors('guest');
        $this->assertModelExists($guest);
        $this->assertSame(['attending', 'pending', 'pending', 'pending'], array_column($this->state($url)['guests'], 'status'));
        $this->get('/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/guests')->assertInertia(fn (Assert $page) => $page->where('groups.data.0.guests.0.has_response', true));
        $this->delete($base)->assertSessionHasErrors('invitation_link');
        $this->assertModelExists($group);
    }

    public function test_roster_or_label_changes_reject_old_forms_and_removed_pending_members_are_not_resurrected(): void
    {
        [$owner, $wedding, $group, $url] = $this->fixture();
        $state = $this->state($url);
        $guest = $group->guests()->orderBy('id')->first();
        $base = '/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/invitation-groups/'.$group->id;
        $this->actingAs($owner)->patch($base, ['label' => 'Updated household'])->assertRedirect();
        $this->postJson($url.'/rsvp', $this->payload($state, [0 => 'attending']))->assertUnprocessable();
        $next = $this->state($url);
        $this->delete($base.'/guests/'.$guest->id)->assertRedirect();
        $this->postJson($url.'/rsvp', $this->payload($next, [0 => 'attending']))->assertUnprocessable();
        $this->assertModelMissing($guest);
        $this->assertDatabaseCount('rsvp_responses', 0);
    }

    public function test_storage_prevents_duplicate_responses_and_deleting_a_responded_guest(): void
    {
        [, , $group] = $this->fixture();
        $guest = $group->guests()->first();
        RsvpResponse::factory()->for($guest)->create();
        foreach ([fn () => DB::table('rsvp_responses')->insert(['guest_id' => $guest->id, 'status' => 'declined', 'responded_at' => now()]), fn () => DB::table('guests')->where('id', $guest->id)->delete()] as $operation) {
            try {
                $operation();
                $this->fail('The database must preserve response uniqueness and retention.');
            } catch (QueryException) {
                $this->assertDatabaseCount('rsvp_responses', 1);
                $this->assertModelExists($guest);
            }
        }
    }

    public function test_publication_snapshots_remain_unchanged_and_private_edits_never_enter_rsvp_output(): void
    {
        [, $wedding, , $url] = $this->fixture();
        $snapshot = $wedding->websiteRevisions()->sole()->getAttributes();
        $wedding->update(['partner_one_name' => 'UNPUBLISHED FACT']);
        $draft = $wedding->websiteDraft;
        $content = $draft->content;
        $content['introduction_message'] = 'UNSAVED TO PUBLIC';
        $draft->update(['content' => $content, 'lock_version' => 2]);
        $this->postJson($url.'/rsvp', $this->payload($this->state($url), [0 => 'attending']))->assertOk();
        $this->get($url)->assertOk()->assertDontSee('UNPUBLISHED FACT')->assertDontSee('UNSAVED TO PUBLIC');
        $this->assertSame($snapshot, $wedding->websiteRevisions()->sole()->getAttributes());
        $this->publish($wedding->fresh());
        $this->get($url)->assertOk()->assertSee('UNPUBLISHED FACT')->assertSee('UNSAVED TO PUBLIC');
        $this->assertDatabaseCount('rsvp_responses', 1);
    }

    public function test_csrf_setup_is_private_submission_requires_csrf_and_does_not_store_a_bearer_url(): void
    {
        [, , , $url] = $this->fixture();
        $input = $this->payload($this->state($url), [0 => 'attending']);
        $this->app->instance('env', 'local');
        $this->postJson($url.'/rsvp', $input)->assertStatus(419)->assertHeader('Referrer-Policy', 'no-referrer')->assertDontSee(basename($url));
        $setup = $this->getJson(route('public.invitations.csrf'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $token = $setup->json('csrfToken');
        $this->assertIsString($token);
        $this->postJson($url.'/rsvp', $input, ['X-CSRF-TOKEN' => $token])->assertOk();
        $previous = session()->previousUrl();
        $this->assertStringNotContainsString(basename($url), $previous);
        $this->get($url)->assertOk();
        $this->assertSame($previous, session()->previousUrl());
    }

    public function test_submission_rate_limit_uses_private_generic_errors_and_keeps_saved_data(): void
    {
        $this->freezeTime();
        [, , , $url] = $this->fixture();
        $input = $this->payload($this->state($url), [0 => 'attending']);
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->postJson($url.'/rsvp', $input)->assertOk();
        }
        $before = RsvpResponse::query()->sole()->getAttributes();
        $this->postJson($url.'/rsvp', $input)->assertTooManyRequests()->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Retry-After', '60')->assertJsonPath('retryAfter', 60)
            ->assertJsonPath('message', 'Too many requests. Try again in 60 seconds. Your saved responses have not changed.')
            ->assertDontSee(basename($url));
        $this->assertDatabaseCount('rsvp_responses', 1);
        $this->travel(61)->seconds();
        $this->postJson($url.'/rsvp', $input)->assertOk();
        $this->assertSame($before, RsvpResponse::query()->sole()->getAttributes());
    }

    public function test_exhausted_group_does_not_block_another_group_at_the_same_ip(): void
    {
        [, $wedding, , $url] = $this->fixture();
        $other = InvitationGroup::factory()->for($wedding)->withGuests()->create();
        $otherUrl = app(InvitationLink::class)->execute($wedding, $other, 'generate', 0)['url'];
        $input = $this->payload($this->state($url), [0 => 'attending']);
        $otherInput = $this->payload($this->state($otherUrl), [0 => 'attending']);
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->postJson($url.'/rsvp', $input)->assertOk();
        }
        $this->postJson($url.'/rsvp', $input)->assertTooManyRequests();
        $this->postJson($otherUrl.'/rsvp', $otherInput)->assertOk();
        $this->assertDatabaseCount('rsvp_responses', 2);
    }

    public function test_csrf_setup_and_several_normal_submissions_use_independent_allowances(): void
    {
        [, , , $url] = $this->fixture();
        $state = $this->state($url);
        $this->app->instance('env', 'local');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $csrf = $this->getJson(route('public.invitations.csrf'))->assertOk()->json('csrfToken');
            $state = $this->postJson($url.'/rsvp', $this->payload($state, [0 => $attempt % 2 === 0 ? 'attending' : 'declined']), ['X-CSRF-TOKEN' => $csrf])
                ->assertOk()->json('rsvp');
        }
        $this->assertSame(5, $state['version']);
        $this->assertSame(['attending', 'pending', 'pending'], array_column($state['guests'], 'status'));
        $this->assertDatabaseCount('rsvp_responses', 1);
    }

    public function test_exhausted_csrf_setup_does_not_consume_the_submission_allowance(): void
    {
        [, , , $url] = $this->fixture();
        $input = $this->payload($this->state($url), [0 => 'attending']);
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->getJson(route('public.invitations.csrf'))->assertOk();
        }
        $this->getJson(route('public.invitations.csrf'))->assertTooManyRequests();
        $this->postJson($url.'/rsvp', $input)->assertOk();
        $this->assertDatabaseCount('rsvp_responses', 1);
    }

    public function test_changing_ip_does_not_bypass_the_same_invitation_limit(): void
    {
        [, , , $url] = $this->fixture();
        $input = $this->payload($this->state($url), [0 => 'attending']);
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->postJson($url.'/rsvp', $input)->assertOk();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])->postJson($url.'/rsvp', $input)->assertTooManyRequests();
        $this->assertDatabaseCount('rsvp_responses', 1);
    }

    public function test_broad_ip_guard_limits_abuse_using_many_different_invalid_tokens(): void
    {
        for ($attempt = 0; $attempt < 120; $attempt++) {
            $token = str_pad(dechex($attempt), 64, '0', STR_PAD_LEFT);
            $this->postJson('/invite/'.$token.'/rsvp', [])->assertNotFound();
        }
        $this->postJson('/invite/'.str_repeat('f', 64).'/rsvp', [])->assertTooManyRequests()
            ->assertHeader('X-RateLimit-Limit', '120')->assertJsonStructure(['message', 'retryAfter']);
        $this->assertDatabaseCount('rsvp_responses', 0);
    }

    private function fixture(): array
    {
        $owner = User::factory()->create();
        $account = Account::create(['name' => 'RSVP workspace']);
        $account->users()->attach($owner, ['role' => 'owner']);
        $wedding = $account->weddings()->create(['partner_one_name' => 'Published Alex', 'partner_two_name' => 'Published Jamie', 'wedding_date' => '2027-05-12', 'public_slug' => 'rsvp-'.fake()->uuid()]);
        $wedding->websiteDraft()->create(['schema_version' => 1, 'lock_version' => 1, 'content' => WebsiteDraft::defaultContent()]);
        $this->publish($wedding);
        $group = InvitationGroup::factory()->for($wedding)->withGuests(3)->create(['label' => 'Santos Family', 'contact_email' => 'private-contact@example.test', 'contact_phone' => '+63 private phone']);
        $url = app(InvitationLink::class)->execute($wedding, $group, 'generate', 0)['url'];

        return [$owner, $wedding->fresh(), $group->fresh(), $url];
    }

    private function state(string $url): array
    {
        return $this->get($url)->assertOk()->viewData('rsvp');
    }

    private function payload(array $state, array $statuses): array
    {
        $responses = [];
        foreach ($statuses as $index => $status) {
            $responses[] = ['guest' => $state['guests'][$index]['reference'], 'status' => $status];
        }

        return ['version' => $state['version'], 'responses' => $responses];
    }

    private function publish(Wedding $wedding): void
    {
        $publisher = app(WeddingPublication::class);
        $summary = $publisher->summary($wedding);
        $publisher->publish($wedding, ['draft_version' => $summary['draftVersion'], 'publication_version' => $summary['publicationVersion'], 'snapshot_token' => $summary['snapshotToken']]);
    }
}
