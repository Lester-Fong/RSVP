<?php

namespace Tests\Feature;

use App\Exceptions\PersonalRsvpValidationException;
use App\Models\Account;
use App\Models\Guest;
use App\Models\InvitationGroup;
use App\Models\RsvpResponse;
use App\Models\User;
use App\Models\WebsiteDraft;
use App\Services\GuestInvitationLifecycle;
use App\Services\InvitationEligibility;
use App\Services\PersonalInvitationLink;
use App\Services\PersonalRsvp;
use App\Services\PersonalRsvpHttp;
use App\Services\PersonalRsvpTransaction;
use App\Services\RsvpDelegationManager;
use App\Services\RsvpMode;
use App\Services\WeddingPublication;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;
use Throwable;

class GuestInvitationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(InvitationEligibility::class, new InvitationEligibility(enabled: true));
    }

    public function test_default_context_cannot_withdraw_and_preserves_unmigrated_rsvp_and_crud(): void
    {
        $fixture = $this->fixture();
        [$owner, $wedding, $group, $guests] = $fixture;
        $token = $this->link($fixture, 0)['token'];
        $this->app->forgetInstance(InvitationEligibility::class);
        $this->removeColumn();
        DB::enableQueryLog();

        app(RsvpMode::class)->assertSchema();
        $this->assertFalse(app(InvitationEligibility::class)->enabled);
        $this->assertSame('Juan', app(PersonalRsvp::class)->read($token)['greeting']['name']);
        $this->assertSame($token, $this->link($fixture, 0, 'copy')['token']);
        $guests[1]->update(['name' => 'Updated Maria']);
        $guests[2]->delete();
        $this->assertSame('Updated Maria', $guests[1]->fresh()->name);
        $this->assertDatabaseMissing('guests', ['id' => $guests[2]->id]);
        $this->unavailable(fn () => $this->inspect($fixture, 0, 'withdraw'), 503);
        $this->assertStringNotContainsString('withdrawn_at', implode("\n", array_column(DB::getQueryLog(), 'query')));
        DB::disableQueryLog();
    }

    public function test_enabled_context_rejects_missing_schema_and_unloaded_attributes(): void
    {
        $fixture = $this->fixture();
        $token = $this->link($fixture, 0)['token'];
        $partial = Guest::query()->select(['id', 'name'])->findOrFail($fixture[3][0]->id);
        $this->unavailable(fn () => app(InvitationEligibility::class)->active($partial), 503);
        $this->unavailable(fn () => $partial->delete(), 503);
        $this->assertDatabaseHas('guests', ['id' => $partial->id]);
        $this->removeColumn();

        $this->unavailable(fn () => app(RsvpMode::class)->assertSchema(), 503);
        $this->unavailable(fn () => app(PersonalRsvp::class)->read($token), 503);
        $this->unavailable(fn () => $this->link($fixture, 0, 'copy'), 503);
        $this->unavailable(fn () => $this->inspect($fixture, 0, 'withdraw'), 503);
        $this->unavailable(fn () => $fixture[2]->delete(), 503);
    }

    #[DataProvider('answers')]
    public function test_unissued_lifecycle_preserves_each_response_state_and_all_response_timestamps(?string $status): void
    {
        $this->freezeTime();
        $fixture = $this->fixture();
        $guest = $fixture[3][0];
        if ($status !== null) {
            RsvpResponse::factory()->for($guest)->create(['status' => $status, 'responded_at' => '2026-10-01 12:00:00']);
        }
        $responses = $this->rows('rsvp_responses');
        $version = $guest->fresh()->rsvp_version;

        $result = $this->change($fixture, 0, 'withdraw');
        $this->assertTrue($result['changed']);
        $this->assertSame('withdrawn', $result['state']);
        $this->assertSame($status ?? 'pending', $result['status']);
        $this->assertNotNull($guest->fresh()->withdrawn_at);
        $this->assertSame(1, $guest->fresh()->rsvp_access_version);
        $result = $this->change($fixture, 0, 'reinstate');
        $this->assertSame('active', $result['state']);
        $this->assertNull($guest->fresh()->withdrawn_at);
        $this->assertSame(2, $guest->fresh()->rsvp_access_version);
        $this->assertSame($version, $guest->fresh()->rsvp_version);
        $this->assertSame($responses, $this->rows('rsvp_responses'));
        $this->assertDatabaseCount('guest_invitation_links', 0);
        $this->assertDatabaseCount('rsvp_delegations', 0);
    }

    public static function answers(): array
    {
        return ['pending' => [null], 'attending' => ['attending'], 'declined' => ['declined']];
    }

    #[DataProvider('withdrawnParties')]
    public function test_representation_changes_invalidate_batches_once_and_preserve_independent_authority(string $party): void
    {
        $fixture = $this->fixture();
        $guests = $fixture[3];
        $juan = $this->link($fixture, 0)['token'];
        $maria = $this->link($fixture, 1)['token'];
        $mariaState = app(PersonalRsvp::class)->read($maria);
        $this->delegate($fixture, 2, 0);
        $this->delegate($fixture, 3, 0);
        $state = app(PersonalRsvp::class)->read($juan);
        app(PersonalRsvp::class)->submit($juan, $this->payload($state, [0 => 'attending', 1 => 'declined']));
        $state = app(PersonalRsvp::class)->read($juan);
        $responses = $this->rows('rsvp_responses');
        $versions = array_map(fn (Guest $guest): int => $guest->fresh()->rsvp_access_version, $guests);
        $index = $party === 'holder' ? 0 : 2;
        $this->change($fixture, $index, 'withdraw');

        foreach ($guests as $position => $guest) {
            $affected = $party === 'holder' ? [0, 2, 3] : [0, 2];
            $this->assertSame($versions[$position] + (int) in_array($position, $affected, true), $guest->fresh()->rsvp_access_version);
        }
        $this->assertSame($responses, $this->rows('rsvp_responses'));
        $this->assertNull($guests[2]->fresh()->rsvpDelegation->representative_guest_id);
        if ($party === 'holder') {
            $this->assertNull($guests[3]->fresh()->rsvpDelegation->representative_guest_id);
            $this->assertNull($guests[2]->fresh()->withdrawn_at);
            $this->assertNull($guests[2]->fresh()->invitationLink);
            $this->unavailable(fn () => app(PersonalRsvp::class)->submit($juan, $this->payload($state, [0 => 'declined'])), 404);
        } else {
            $this->assertSame(['Juan', 'Andrea'], array_column(app(PersonalRsvp::class)->read($juan)['guests'], 'name'));
            $this->rejected(fn () => app(PersonalRsvp::class)->submit($juan, $this->payload($state, [0 => 'declined', 1 => 'attending'])), 'stale_access');
        }
        $this->assertSame($responses, $this->rows('rsvp_responses'));
        app(PersonalRsvp::class)->submit($maria, $this->payload($mariaState, [0 => 'attending']));
        $this->assertDatabaseHas('rsvp_responses', ['guest_id' => $guests[1]->id, 'status' => 'attending']);
        $this->change($fixture, $index, 'reinstate');
        $this->assertNull($guests[$index]->fresh()->rsvpDelegation?->representative_guest_id);
    }

    public static function withdrawnParties(): array
    {
        return ['holder' => ['holder'], 'dependent' => ['dependent']];
    }

    public function test_revoked_credentials_stay_retained_and_current_retries_are_noops_with_aba_protection(): void
    {
        $this->freezeTime();
        $fixture = $this->fixture();
        $guest = $fixture[3][0];
        $token = $this->link($fixture, 0)['token'];
        $state = app(PersonalRsvp::class)->read($token);
        app(PersonalRsvp::class)->submit($token, $this->payload($state, [0 => 'attending']));
        $old = $this->inspect($fixture, 0, 'withdraw')['confirmation'];
        $this->change($fixture, 0, 'withdraw', $old);
        $this->assertNull($guest->fresh()->invitationLink->token_hash);
        $this->assertNull($guest->fresh()->invitationLink->getRawOriginal('token'));
        $this->assertSame(2, $guest->fresh()->invitationLink->version);
        $before = $this->snapshot();
        $this->assertFalse($this->change($fixture, 0, 'withdraw')['changed']);
        $this->assertSame($before, $this->snapshot());
        $this->rejected(fn () => $this->change($fixture, 0, 'withdraw', $old), 'stale_access');
        $this->change($fixture, 0, 'reinstate');
        $this->rejected(fn () => $this->change($fixture, 0, 'withdraw', $old), 'stale_access');
        $this->unavailable(fn () => app(PersonalRsvp::class)->read($token), 404);
        $fresh = $this->link($fixture, 0, 'regenerate')['token'];
        $this->assertNotSame($token, $fresh);
        $this->assertSame('attending', app(PersonalRsvp::class)->read($fresh)['guests'][0]['status']);
        $before = $this->snapshot();
        $this->assertFalse($this->change($fixture, 0, 'reinstate')['changed']);
        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('staleFields')]
    public function test_exact_state_people_and_versions_return_safe_stale_feedback(string $field): void
    {
        $fixture = $this->fixture();
        $input = $this->inspect($fixture, 0, 'withdraw')['confirmation'];
        if ($field === 'state') {
            $input['expectedState'] = 'withdrawn';
        } elseif ($field === 'guestId') {
            $input['expectedGuests'][0]['guestId'] = $fixture[3][1]->id;
        } else {
            $input['expectedGuests'][0][$field]++;
        }
        $before = $this->snapshot();
        $error = $this->rejected(fn () => $this->change($fixture, 0, 'withdraw', $input), 'stale_access');
        $response = PersonalRsvpHttp::render($error, Request::create('/accounts/1/weddings/1/invitation-groups/1/rsvp-access/withdraw', 'POST'));
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('stale_access', $response->getData(true)['kind']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($before, $this->snapshot());
    }

    public static function staleFields(): array
    {
        return [['state'], ['guestId'], ['accessVersion'], ['responseVersion'], ['linkVersion']];
    }

    #[DataProvider('invalidConfirmations')]
    public function test_invalid_confirmation_payloads_make_no_changes(string $variant): void
    {
        $fixture = $this->fixture();
        $input = $this->inspect($fixture, 0, 'withdraw')['confirmation'];
        if ($variant === 'unknown') {
            $input['wedding_id'] = $fixture[1]->id;
        } elseif ($variant === 'nested') {
            $input['expectedGuests'][0]['withdrawn_at'] = null;
        } elseif ($variant === 'missing') {
            unset($input['expectedGuests'][0]['responseVersion']);
        } elseif ($variant === 'duplicate') {
            $input['expectedGuests'][] = $input['expectedGuests'][0];
        } elseif ($variant === 'empty') {
            $input['expectedGuests'] = [];
        } elseif ($variant === 'limit') {
            $input['expectedGuests'] = array_fill(0, 101, $input['expectedGuests'][0]);
        } else {
            $input['expectedGuests'][0]['accessVersion'] = $variant === 'negative' ? -1 : '0';
        }
        $before = $this->snapshot();
        $this->rejected(fn () => $this->change($fixture, 0, 'withdraw', $input), 'validation');
        $this->assertSame($before, $this->snapshot());
    }

    public static function invalidConfirmations(): array
    {
        return [['unknown'], ['nested'], ['missing'], ['duplicate'], ['empty'], ['limit'], ['negative'], ['numeric-string']];
    }

    #[DataProvider('changedDetails')]
    public function test_real_changes_after_inspection_require_new_confirmation(string $change): void
    {
        $fixture = $this->fixture();
        $token = $this->link($fixture, 0)['token'];
        $input = $this->inspect($fixture, 0, 'withdraw')['confirmation'];
        if ($change === 'rename') {
            $fixture[3][0]->update(['name' => 'Updated Juan']);
        } elseif ($change === 'answer') {
            app(PersonalRsvp::class)->submit($token, $this->payload(app(PersonalRsvp::class)->read($token), [0 => 'attending']));
        } elseif ($change === 'rotate') {
            $this->link($fixture, 0, 'regenerate');
        } else {
            $this->delegate($fixture, 2, 0);
        }
        $before = $this->snapshot();
        $this->rejected(fn () => $this->change($fixture, 0, 'withdraw', $input), 'stale_access');
        $this->assertSame($before, $this->snapshot());
    }

    public static function changedDetails(): array
    {
        return [['rename'], ['answer'], ['rotate'], ['dependent']];
    }

    public function test_foreign_owners_weddings_groups_and_members_cannot_change_state(): void
    {
        $fixture = $this->fixture();
        [$owner, $wedding, $group, $guests] = $fixture;
        [$foreignOwner, $foreignWedding, $foreignGroup, $foreignGuests] = $this->fixture();
        $service = app(GuestInvitationLifecycle::class);
        $input = $this->inspect($fixture, 0, 'withdraw')['confirmation'];
        $before = $this->snapshot();
        $this->exception(fn () => $service->withdraw($foreignOwner, $wedding, $group, $guests[0], $input), AuthorizationException::class);
        $this->exception(fn () => $service->inspect($owner, $wedding, $foreignGroup, $foreignGuests[0], 'withdraw'), ModelNotFoundException::class);
        $this->unavailable(fn () => $service->withdraw($owner, $wedding, $group, $foreignGuests[0], $input), 404);
        $this->exception(fn () => $service->reinstate($foreignOwner, $wedding, $group, $guests[0], $input), AuthorizationException::class);
        $this->exception(fn () => $service->inspect($owner, $foreignWedding, $foreignGroup, $foreignGuests[0], 'withdraw'), AuthorizationException::class);
        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('credentialActions')]
    public function test_withdrawn_state_blocks_issuance_recovery_and_rotation_even_with_residual_material(string $action): void
    {
        $fixture = $this->fixture();
        $this->link($fixture, 0);
        DB::table('guests')->where('id', $fixture[3][0]->id)->update(['withdrawn_at' => '2026-10-21 12:00:00']);
        $before = $this->snapshot();
        $this->rejected(fn () => $this->link($fixture, 0, $action), 'validation');
        $this->assertSame($before, $this->snapshot());
    }

    public static function credentialActions(): array
    {
        return [['generate'], ['copy'], ['regenerate']];
    }

    #[DataProvider('withdrawnParties')]
    public function test_all_rsvp_targets_are_rechecked_before_unchanged_retries(string $party): void
    {
        $fixture = $this->fixture();
        $token = $this->link($fixture, 0)['token'];
        $this->delegate($fixture, 2, 0);
        $input = $this->payload(app(PersonalRsvp::class)->read($token), [0 => 'attending', 1 => 'declined']);
        app(PersonalRsvp::class)->submit($token, $input);
        DB::table('guests')->where('id', $fixture[3][$party === 'holder' ? 0 : 2]->id)->update(['withdrawn_at' => '2026-10-21 12:00:00']);
        $before = $this->snapshot();
        $this->unavailable(fn () => app(PersonalRsvp::class)->read($token), 404);
        $this->unavailable(fn () => app(PersonalRsvp::class)->page($token), 404);
        $this->unavailable(fn () => app(PersonalRsvp::class)->submit($token, $input), 404);
        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('withdrawnParties')]
    public function test_withdrawn_representatives_or_dependents_cannot_receive_designations(string $party): void
    {
        $fixture = $this->fixture();
        $this->link($fixture, 0);
        $this->change($fixture, $party === 'holder' ? 0 : 2, 'withdraw');
        $before = $this->snapshot();
        $this->rejected(fn () => $this->delegate($fixture, 2, 0), 'validation');
        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('failurePhases')]
    public function test_failures_after_real_credential_or_version_writes_roll_back_everything(string $method): void
    {
        $fixture = $this->fixture();
        $this->link($fixture, 0);
        $this->delegate($fixture, 2, 0);
        $input = $this->inspect($fixture, 0, 'withdraw')['confirmation'];
        $before = $this->snapshot();
        $transactions = Mockery::mock(PersonalRsvpTransaction::class)->makePartial();
        $transactions->shouldReceive($method)->once()->andReturnUsing(function ($argument) use ($method): never {
            (new PersonalRsvpTransaction)->{$method}($argument);
            throw new RuntimeException('Injected lifecycle failure.');
        });
        $service = new GuestInvitationLifecycle($transactions, app(InvitationEligibility::class));
        $this->exception(fn () => $service->withdraw($fixture[0], $fixture[1], $fixture[2], $fixture[3][0], $input), RuntimeException::class);
        $this->assertSame($before, $this->snapshot());
    }

    public static function failurePhases(): array
    {
        return [['clearCredential'], ['bumpAccess']];
    }

    #[DataProvider('overflowFields')]
    public function test_version_overflow_is_rejected_before_any_mutation(string $field): void
    {
        $fixture = $this->fixture();
        $this->link($fixture, 0);
        if ($field === 'access') {
            DB::table('guests')->where('id', $fixture[3][0]->id)->update(['rsvp_access_version' => PersonalRsvpTransaction::MAX_VERSION]);
        } else {
            DB::table('guest_invitation_links')->where('guest_id', $fixture[3][0]->id)->update(['version' => PersonalRsvpTransaction::MAX_VERSION]);
        }
        $before = $this->snapshot();
        $this->rejected(fn () => $this->change($fixture, 0, 'withdraw'), 'validation');
        $this->assertSame($before, $this->snapshot());
    }

    public static function overflowFields(): array
    {
        return [['access'], ['credential']];
    }

    public function test_unreadable_ciphertext_can_be_withdrawn_without_decryption(): void
    {
        $fixture = $this->fixture();
        $this->link($fixture, 0);
        DB::table('guest_invitation_links')->where('guest_id', $fixture[3][0]->id)->update(['token' => 'unreadable-fixture-ciphertext']);
        $this->change($fixture, 0, 'withdraw');
        $this->assertNull($fixture[3][0]->fresh()->invitationLink->getRawOriginal('token'));
    }

    public function test_withdrawn_unissued_people_and_their_parents_are_retained_until_the_narrow_reinstatement_exception(): void
    {
        $fixture = $this->fixture();
        $this->change($fixture, 0, 'withdraw');
        $before = $this->snapshot();
        foreach ([$fixture[3][0]->fresh(), $fixture[2], $fixture[1]] as $model) {
            $this->exception(fn () => $model->delete(), ValidationException::class);
            $this->assertSame($before, $this->snapshot());
        }
        $this->change($fixture, 0, 'reinstate');
        $fixture[3][0]->fresh()->delete();
        $this->assertDatabaseMissing('guests', ['id' => $fixture[3][0]->id]);
    }

    public function test_issued_and_delegated_records_remain_protected_after_reinstatement(): void
    {
        $fixture = $this->fixture();
        $this->link($fixture, 0);
        $this->delegate($fixture, 2, 0);
        $this->change($fixture, 0, 'withdraw');
        $this->change($fixture, 0, 'reinstate');
        $this->exception(fn () => $fixture[3][0]->fresh()->delete(), ValidationException::class);
        $this->exception(fn () => $fixture[3][2]->fresh()->delete(), ValidationException::class);
    }

    public function test_inconsistent_withdrawn_authority_fails_closed_and_restrict_only_cleanup_is_allowed(): void
    {
        $fixture = $this->fixture();
        $this->link($fixture, 0);
        DB::table('guests')->where('id', $fixture[3][0]->id)->update(['withdrawn_at' => '2026-10-21 12:00:00']);
        $before = $this->snapshot();
        $this->rejected(fn () => $this->change($fixture, 0, 'reinstate'), 'validation');
        $this->assertSame($before, $this->snapshot());
        $this->link($fixture, 0, 'revoke');
        $this->change($fixture, 0, 'reinstate');
        $this->assertNull($fixture[3][0]->fresh()->invitationLink->getRawOriginal('token'));
    }

    public function test_unpublished_owners_can_manage_state_without_changing_snapshots_or_recovering_tokens(): void
    {
        $fixture = $this->fixture();
        $token = $this->link($fixture, 0)['token'];
        app(WeddingPublication::class)->unpublish($fixture[1], $fixture[1]->fresh()->publication_version);
        $before = [$this->rows('weddings'), $this->rows('website_revisions'), $this->rows('website_drafts')];
        $inspection = $this->inspect($fixture, 0, 'withdraw');
        $this->assertStringNotContainsString($token, json_encode($inspection));
        $this->assertStringNotContainsString('token_hash', json_encode($inspection));
        $this->assertSame(['guest', 'state', 'withdrawnAt', 'status', 'respondedAt', 'action', 'affected', 'confirmation'], array_keys($inspection));
        $this->change($fixture, 0, 'withdraw');
        $this->change($fixture, 0, 'reinstate');
        $this->assertSame($before, [$this->rows('weddings'), $this->rows('website_revisions'), $this->rows('website_drafts')]);
        $this->unavailable(fn () => app(PersonalRsvp::class)->read($token), 404);
    }

    #[DataProvider('affectedChanges')]
    public function test_missing_extra_and_changed_dependent_confirmations_are_rejected(string $change): void
    {
        $fixture = $this->fixture();
        $token = $this->link($fixture, 0)['token'];
        $this->delegate($fixture, 2, 0);
        $input = $this->inspect($fixture, 0, 'withdraw')['confirmation'];
        if ($change === 'missing') {
            array_pop($input['expectedGuests']);
        } elseif ($change === 'extra') {
            $input['expectedGuests'][] = ['guestId' => $fixture[3][1]->id, 'accessVersion' => 0, 'responseVersion' => 0, 'linkVersion' => 0];
        } else {
            app(PersonalRsvp::class)->submit($token, $this->payload(app(PersonalRsvp::class)->read($token), [1 => 'attending']));
        }
        $before = $this->snapshot();
        $this->rejected(fn () => $this->change($fixture, 0, 'withdraw', $input), 'stale_access');
        $this->assertSame($before, $this->snapshot());
    }

    public static function affectedChanges(): array
    {
        return [['missing'], ['extra'], ['dependent-response']];
    }

    public function test_confirmation_person_order_is_irrelevant_and_reassignment_invalidates_the_old_set(): void
    {
        $fixture = $this->fixture();
        $this->link($fixture, 0);
        $this->link($fixture, 1);
        $this->delegate($fixture, 2, 0);
        $old = $this->inspect($fixture, 2, 'withdraw')['confirmation'];
        $this->delegate($fixture, 2, 1);
        $before = $this->snapshot();
        $this->rejected(fn () => $this->change($fixture, 2, 'withdraw', $old), 'stale_access');
        $this->assertSame($before, $this->snapshot());
        $input = $this->inspect($fixture, 2, 'withdraw')['confirmation'];
        $input['expectedGuests'] = array_reverse($input['expectedGuests']);
        $this->assertTrue($this->change($fixture, 2, 'withdraw', $input)['changed']);
    }

    public function test_committed_withdrawal_cannot_be_bypassed_by_a_previously_loaded_guest_model(): void
    {
        $fixture = $this->fixture();
        $stale = $fixture[3][0]->fresh();
        $this->change($fixture, 0, 'withdraw');
        $before = $this->snapshot();
        $this->exception(fn () => $stale->delete(), ValidationException::class);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_reinstatement_rolls_back_if_version_persistence_fails(): void
    {
        $fixture = $this->fixture();
        $this->change($fixture, 0, 'withdraw');
        $input = $this->inspect($fixture, 0, 'reinstate')['confirmation'];
        $before = $this->snapshot();
        $transactions = Mockery::mock(PersonalRsvpTransaction::class)->makePartial();
        $transactions->shouldReceive('bumpAccess')->once()->andReturnUsing(function (array $guests): never {
            (new PersonalRsvpTransaction)->bumpAccess($guests);
            throw new RuntimeException('Injected reinstatement failure.');
        });
        $service = new GuestInvitationLifecycle($transactions, app(InvitationEligibility::class));
        $this->exception(fn () => $service->reinstate($fixture[0], $fixture[1], $fixture[2], $fixture[3][0], $input), RuntimeException::class);
        $this->assertSame($before, $this->snapshot());
    }

    private function fixture(): array
    {
        $owner = User::factory()->create();
        $account = Account::create(['name' => 'Synthetic lifecycle workspace']);
        $account->users()->attach($owner, ['role' => 'owner']);
        $wedding = $account->weddings()->create(['partner_one_name' => 'Alex', 'partner_two_name' => 'Jamie', 'wedding_date' => '2027-05-12', 'public_slug' => fake()->uuid()]);
        $wedding->websiteDraft()->create(['schema_version' => 1, 'lock_version' => 0, 'content' => WebsiteDraft::defaultContent()]);
        $publisher = app(WeddingPublication::class);
        $summary = $publisher->summary($wedding);
        $publisher->publish($wedding, ['draft_version' => 0, 'publication_version' => 0, 'snapshot_token' => $summary['snapshotToken']]);
        $group = InvitationGroup::factory()->for($wedding)->has(Guest::factory()->count(4)->sequence(['name' => 'Juan'], ['name' => 'Maria'], ['name' => 'Miguel'], ['name' => 'Andrea']))->create();

        return [$owner, $wedding->fresh(), $group, $group->guests->all()];
    }

    private function inspect(array $fixture, int $index, string $action): array
    {
        return app(GuestInvitationLifecycle::class)->inspect($fixture[0], $fixture[1], $fixture[2], $fixture[3][$index], $action);
    }

    private function change(array $fixture, int $index, string $action, ?array $input = null): array
    {
        return app(GuestInvitationLifecycle::class)->{$action}($fixture[0], $fixture[1], $fixture[2], $fixture[3][$index], $input ?? $this->inspect($fixture, $index, $action)['confirmation']);
    }

    private function link(array $fixture, int $index, string $action = 'generate'): array
    {
        $guest = $fixture[3][$index]->fresh();

        return app(PersonalInvitationLink::class)->execute($fixture[0], $fixture[1], $fixture[2], $guest, $action, $guest->invitationLink?->version ?? 0, $guest->rsvp_access_version);
    }

    private function delegate(array $fixture, int $dependentIndex, int $representativeIndex): void
    {
        $dependent = $fixture[3][$dependentIndex];
        $representative = $fixture[3][$representativeIndex];
        $previous = $dependent->fresh()->rsvpDelegation?->representative;
        $versions = collect(array_filter([$dependent, $previous, $representative]))->mapWithKeys(fn (Guest $guest): array => [$guest->id => $guest->fresh()->rsvp_access_version])->all();
        app(RsvpDelegationManager::class)->assign($fixture[0], $fixture[1], $fixture[2], $dependent, $representative, $versions, $dependent->fresh()->invitationLink?->version ?? 0);
    }

    private function payload(array $state, array $statuses): array
    {
        $responses = [];
        foreach ($statuses as $index => $status) {
            $responses[] = ['guest' => $state['guests'][$index]['reference'], 'version' => $state['guests'][$index]['version'], 'status' => $status];
        }

        return ['access_version' => $state['access_version'], 'responses' => $responses];
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['guests', 'guest_invitation_links', 'rsvp_delegations', 'rsvp_responses'] as $table) {
            $snapshot[$table] = $this->rows($table);
        }

        return $snapshot;
    }

    private function rows(string $table): array
    {
        return DB::table($table)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
    }

    private function removeColumn(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        (require database_path('migrations/2026_10_21_142258_add_withdrawn_at_to_guests_table.php'))->down();
    }

    private function rejected(callable $action, string $kind): PersonalRsvpValidationException
    {
        $error = $this->exception($action, PersonalRsvpValidationException::class);
        $this->assertSame($kind, $error->kind);

        return $error;
    }

    private function exception(callable $action, string $class): Throwable
    {
        try {
            $action();
        } catch (Throwable $error) {
            $this->assertInstanceOf($class, $error);

            return $error;
        }
        $this->fail('The operation must be rejected.');
    }

    private function unavailable(callable $action, int $status): void
    {
        try {
            $action();
        } catch (ModelNotFoundException) {
            $this->assertSame(404, $status);

            return;
        } catch (HttpExceptionInterface $error) {
            $this->assertSame($status, $error->getStatusCode());

            return;
        }
        $this->fail('The operation must be unavailable.');
    }
}
