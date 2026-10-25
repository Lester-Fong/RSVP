<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Guest;
use App\Models\GuestInvitationLink;
use App\Models\InvitationGroup;
use App\Models\RsvpDelegation;
use App\Models\RsvpResponse;
use App\Models\User;
use App\Models\WebsiteDraft;
use App\Models\Wedding;
use App\Services\InvitationLink;
use App\Services\PersonalInvitationLink;
use App\Services\PersonalRsvp;
use App\Services\PersonalRsvpTransaction;
use App\Services\PublishedWedding;
use App\Services\RsvpDelegationManager;
use App\Services\WeddingAttendance;
use App\Services\WeddingPublication;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;
use Throwable;

class PersonalRsvpServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_issuance_recovery_generation_and_rotation_preserve_unique_encrypted_ownership(): void
    {
        [$owner, $wedding, $group, $guests] = $this->fixture();
        $first = $this->act($owner, $wedding, $group, $guests[0]);
        $second = $this->act($owner, $wedding, $group, $guests[1]);
        $row = DB::table('guest_invitation_links')->where('guest_id', $guests[0]->id)->first();
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/D', $first['token']);
        $this->assertNotSame($first['token'], $second['token']);
        $this->assertSame(hash('sha256', $first['token']), $row->token_hash);
        $this->assertNotSame($first['token'], $row->token);
        $this->assertSame($first['token'], Crypt::decryptString($row->token));
        $this->assertSame(1, $first['version']);
        $this->assertSame(1, $first['access_version']);
        foreach (['copy', 'generate', 'copy'] as $action) {
            $this->assertSame($first, $this->act($owner, $wedding, $group, $guests[0], $action));
        }
        $this->assertSame((array) $row, (array) DB::table('guest_invitation_links')->where('guest_id', $guests[0]->id)->first());
        $rotated = $this->act($owner, $wedding, $group, $guests[0], 'regenerate');
        $this->assertSame(2, $rotated['version']);
        $this->assertNotSame($first['token'], $rotated['token']);
        $this->unavailable(fn () => app(PersonalRsvp::class)->read($first['token']));
        $this->assertSame(2, GuestInvitationLink::count());
        $serialized = $guests[0]->fresh()->load('invitationLink')->toJson();
        foreach ([$rotated['token'], 'token_hash', '"token"'] as $secret) {
            $this->assertStringNotContainsString($secret, $serialized);
        }
    }

    #[DataProvider('lifecycleActions')]
    public function test_all_lifecycle_actions_and_inspection_enforce_owner_and_nested_scope(string $action): void
    {
        [$owner, $wedding, $group, $guests] = $this->fixture();
        $this->act($owner, $wedding, $group, $guests[0]);
        [$foreignOwner, $foreignWedding, $foreignGroup, $foreignGuests] = $this->fixture();
        $viewer = User::factory()->create();
        $wedding->account->users()->attach($viewer, ['role' => 'viewer']);
        foreach ([$viewer, $foreignOwner] as $actor) {
            $this->rejected(fn () => $this->act($actor, $wedding, $group, $guests[0], $action), AuthorizationException::class);
            $this->rejected(fn () => app(RsvpDelegationManager::class)->inspect($actor, $wedding, $group, $guests[0]), AuthorizationException::class);
        }
        $this->unavailable(fn () => $this->act($owner, $wedding, $foreignGroup, $foreignGuests[0], $action));
        $this->unavailable(fn () => $this->act($owner, $wedding, $group, $foreignGuests[0], $action));
        $this->rejected(fn () => $this->act($owner, $foreignWedding, $foreignGroup, $foreignGuests[0], $action), AuthorizationException::class);
    }

    public static function lifecycleActions(): array
    {
        return array_map(fn (string $value): array => [$value], ['generate', 'copy', 'revoke', 'regenerate']);
    }

    public function test_stale_and_invalid_lifecycle_actions_fail_without_changing_state(): void
    {
        [$owner, $wedding, $group, $guests] = $this->fixture();
        foreach (['copy', 'revoke', 'regenerate'] as $action) {
            $this->rejected(fn () => $this->act($owner, $wedding, $group, $guests[0], $action));
        }
        $this->assertDatabaseCount('guest_invitation_links', 0);
        $issued = $this->act($owner, $wedding, $group, $guests[0]);
        foreach (['generate', 'copy', 'revoke', 'regenerate', 'unsupported'] as $action) {
            $this->rejected(fn () => app(PersonalInvitationLink::class)->execute($owner, $wedding, $group, $guests[0], $action, 0, 1));
        }
        $this->rejected(fn () => app(PersonalInvitationLink::class)->execute($owner, $wedding, $group, $guests[0], 'revoke', 1, 0));
        $this->assertSame($issued, $this->act($owner, $wedding, $group, $guests[0], 'copy'));
    }

    public function test_corrupted_ciphertext_or_hash_requires_explicit_regeneration_and_remains_revocable(): void
    {
        [$owner, $wedding, $group, $guests] = $this->fixture();
        $old = $this->act($owner, $wedding, $group, $guests[0]);
        DB::table('guest_invitation_links')->where('guest_id', $guests[0]->id)->update(['token' => 'corrupt-ciphertext']);
        $this->rejected(fn () => $this->act($owner, $wedding, $group, $guests[0], 'copy'));
        $next = $this->act($owner, $wedding, $group, $guests[0], 'regenerate');
        $this->assertNotSame($old['token'], $next['token']);
        DB::table('guest_invitation_links')->where('guest_id', $guests[0]->id)->update(['token_hash' => hash('sha256', 'wrong-hash')]);
        $this->rejected(fn () => $this->act($owner, $wedding, $group, $guests[0], 'copy'));
        $this->assertNull($this->act($owner, $wedding, $group, $guests[0], 'revoke')['token']);
    }

    public function test_designation_reassignment_revoke_and_aba_changes_preserve_answers_and_use_access_versions(): void
    {
        [$owner, $wedding, $group, $guests] = $this->fixture();
        $juan = $this->act($owner, $wedding, $group, $guests[0])['token'];
        $maria = $this->act($owner, $wedding, $group, $guests[1])['token'];
        $own = $this->act($owner, $wedding, $group, $guests[2])['token'];
        RsvpResponse::factory()->for($guests[2])->create(['responded_at' => '2026-10-01 12:00:00']);
        $answer = RsvpResponse::query()->sole()->getAttributes();
        $this->delegate($owner, $wedding, $group, $guests[2], $guests[0]);
        $initial = app(PersonalRsvp::class)->read($juan);
        $this->unavailable(fn () => app(PersonalRsvp::class)->read($own));
        foreach (['generate', 'copy', 'regenerate'] as $action) {
            $this->rejected(fn () => $this->act($owner, $wedding, $group, $guests[2], $action));
        }
        $versions = $this->versions($guests[0], $guests[1], $guests[2]);
        $versions[$guests[0]->id]--;
        $this->rejected(fn () => app(RsvpDelegationManager::class)->assign($owner, $wedding, $group, $guests[2], $guests[1], $versions, 2));
        $this->assertSame($guests[0]->id, $guests[2]->fresh()->rsvpDelegation->representative_guest_id);
        $this->delegate($owner, $wedding, $group, $guests[2], $guests[1]);
        $this->assertCount(1, app(PersonalRsvp::class)->read($juan)['guests']);
        $this->assertCount(2, app(PersonalRsvp::class)->read($maria)['guests']);
        $this->delegate($owner, $wedding, $group, $guests[2], null);
        $this->unavailable(fn () => app(PersonalRsvp::class)->read($own));
        $this->delegate($owner, $wedding, $group, $guests[2], $guests[0]);
        $this->rejected(fn () => app(PersonalRsvp::class)->submit($juan, $this->payload($initial, [0 => 'attending'])));
        $this->assertSame($answer, RsvpResponse::query()->sole()->getAttributes());
        $this->assertDatabaseCount('rsvp_delegations', 1);
    }

    public function test_self_chains_cycles_foreign_groups_missing_independent_access_and_foreign_owners_are_denied(): void
    {
        [$owner, $wedding, $group, $guests] = $this->fixture();
        $this->act($owner, $wedding, $group, $guests[0]);
        $this->act($owner, $wedding, $group, $guests[1]);
        $this->rejected(fn () => $this->delegate($owner, $wedding, $group, $guests[0], $guests[0]));
        $this->rejected(fn () => $this->delegate($owner, $wedding, $group, $guests[3], $guests[2]));
        $this->delegate($owner, $wedding, $group, $guests[2], $guests[0]);
        $this->rejected(fn () => $this->delegate($owner, $wedding, $group, $guests[3], $guests[2]));
        $this->rejected(fn () => $this->delegate($owner, $wedding, $group, $guests[0], $guests[2]));
        $this->rejected(fn () => $this->delegate($owner, $wedding, $group, $guests[0], $guests[1]));
        [$otherOwner, , , $foreign] = $this->fixture();
        $this->unavailable(fn () => $this->delegate($owner, $wedding, $group, $guests[3], $foreign[0]));
        $otherGroup = InvitationGroup::factory()->for($wedding)->withGuests()->create();
        $this->unavailable(fn () => $this->delegate($owner, $wedding, $group, $guests[3], $otherGroup->guests->sole()));
        $this->rejected(fn () => $this->delegate($otherOwner, $wedding, $group, $guests[3], $guests[1]), AuthorizationException::class);
        $this->rejected(fn () => app(RsvpDelegationManager::class)->revoke($otherOwner, $wedding, $group, $guests[2], $this->versions($guests[0], $guests[2]), 0), AuthorizationException::class);
        $this->assertDatabaseCount('rsvp_delegations', 1);
    }

    public function test_current_assignment_and_absent_revoke_are_noops_including_independent_credential_state(): void
    {
        [$owner, $wedding, $group, $guests] = $this->fixture();
        $token = $this->act($owner, $wedding, $group, $guests[0])['token'];
        $this->delegate($owner, $wedding, $group, $guests[0], null);
        $this->assertCount(1, app(PersonalRsvp::class)->read($token)['guests']);
        $this->assertDatabaseCount('rsvp_delegations', 0);
        $this->delegate($owner, $wedding, $group, $guests[2], $guests[0]);
        $before = $this->versions($guests[0], $guests[2]);
        $this->delegate($owner, $wedding, $group, $guests[2], $guests[0]);
        $this->assertSame($before, $this->versions($guests[0], $guests[2]));
        $state = app(RsvpDelegationManager::class)->inspect($owner, $wedding, $group, $guests[0]);
        $this->assertSame([$guests[2]->id], array_column($state['dependents'], 'id'));
        $this->assertStringNotContainsString($token, json_encode($state));
    }

    public function test_representative_rotation_retains_delegations_and_revocation_removes_them_without_answer_loss(): void
    {
        [$owner, $wedding, $group, $guests] = $this->fixture();
        $token = $this->act($owner, $wedding, $group, $guests[0])['token'];
        $this->delegate($owner, $wedding, $group, $guests[2], $guests[0]);
        $this->delegate($owner, $wedding, $group, $guests[3], $guests[0]);
        app(PersonalRsvp::class)->submit($token, $this->payload(app(PersonalRsvp::class)->read($token), [0 => 'attending', 1 => 'declined', 2 => 'attending']));
        $rows = DB::table('rsvp_responses')->orderBy('guest_id')->get()->toJson();
        $next = $this->act($owner, $wedding, $group, $guests[0], 'regenerate')['token'];
        $this->assertCount(3, app(PersonalRsvp::class)->read($next)['guests']);
        $this->act($owner, $wedding, $group, $guests[0], 'revoke');
        $this->unavailable(fn () => app(PersonalRsvp::class)->submit($next, []));
        $this->assertSame(2, RsvpDelegation::whereNull('representative_guest_id')->count());
        $this->assertSame($rows, DB::table('rsvp_responses')->orderBy('guest_id')->get()->toJson());
        $revoked = $this->act($owner, $wedding, $group, $guests[0], 'revoke');
        $this->assertSame($revoked, $this->act($owner, $wedding, $group, $guests[0], 'revoke'));
        $fresh = $this->act($owner, $wedding, $group, $guests[0], 'regenerate')['token'];
        $this->assertCount(1, app(PersonalRsvp::class)->read($fresh)['guests']);
        $this->assertSame(['invited' => 4, 'attending' => 2, 'declined' => 1, 'pending' => 1], app(WeddingAttendance::class)->summary($wedding));
    }

    public function test_self_only_projection_and_independent_same_baseline_submissions_are_private_and_conflict_free(): void
    {
        [$owner, $wedding, $group, $guests] = $this->fixture();
        $juan = $this->act($owner, $wedding, $group, $guests[0])['token'];
        $maria = $this->act($owner, $wedding, $group, $guests[1])['token'];
        $first = app(PersonalRsvp::class)->read($juan);
        $second = app(PersonalRsvp::class)->read($maria);
        $this->assertSame(['Juan'], array_column($first['guests'], 'name'));
        $this->assertSame(['greeting', 'access_version', 'guests'], array_keys($first));
        $this->assertSame('pending', $first['guests'][0]['status']);
        foreach (['Maria', 'Private household', 'private@example.test', 'account_id', 'guest_id', 'wedding_id', 'token', $juan] as $private) {
            $this->assertStringNotContainsString($private, json_encode($first));
        }
        app(PersonalRsvp::class)->submit($juan, $this->payload($first, [0 => 'attending']));
        app(PersonalRsvp::class)->submit($maria, $this->payload($second, [0 => 'declined']));
        $this->assertSame(1, $guests[0]->fresh()->rsvp_version);
        $this->assertSame(1, $guests[1]->fresh()->rsvp_version);
        $this->assertSame(0, $group->fresh()->rsvp_version);
    }

    public function test_partial_updates_and_authorized_identical_retries_preserve_pending_and_timestamps(): void
    {
        [$owner, $wedding, $group, $guests] = $this->fixture();
        $token = $this->act($owner, $wedding, $group, $guests[0])['token'];
        $this->delegate($owner, $wedding, $group, $guests[2], $guests[0]);
        $this->delegate($owner, $wedding, $group, $guests[3], $guests[0]);
        $input = $this->payload(app(PersonalRsvp::class)->read($token), [0 => 'attending', 1 => 'declined']);
        $this->freezeTime();
        $saved = app(PersonalRsvp::class)->submit($token, $input);
        $this->assertSame(['attending', 'declined', 'pending'], array_column($saved['guests'], 'status'));
        $rows = DB::table('rsvp_responses')->orderBy('guest_id')->get()->toJson();
        $this->travel(1)->hours();
        app(PersonalRsvp::class)->submit($token, $input);
        $this->assertSame($rows, DB::table('rsvp_responses')->orderBy('guest_id')->get()->toJson());
        $next = app(PersonalRsvp::class)->submit($token, $this->payload($saved, [0 => 'declined']));
        $this->assertSame(2, $next['guests'][0]['version']);
        $this->assertNotSame($saved['guests'][0]['respondedAt'], $next['guests'][0]['respondedAt']);
        $this->assertSame($saved['guests'][1], $next['guests'][1]);
        $this->assertSame($saved['guests'][2], $next['guests'][2]);
    }

    public function test_stale_changed_target_rolls_back_every_change_and_future_versions_reject_noops(): void
    {
        [$owner, $wedding, $group, $guests] = $this->fixture();
        $token = $this->act($owner, $wedding, $group, $guests[0])['token'];
        $this->delegate($owner, $wedding, $group, $guests[2], $guests[0]);
        $state = app(PersonalRsvp::class)->read($token);
        app(PersonalRsvp::class)->submit($token, $this->payload($state, [0 => 'attending']));
        $this->rejected(fn () => app(PersonalRsvp::class)->submit($token, $this->payload($state, [0 => 'declined', 1 => 'attending'])));
        $this->assertDatabaseCount('rsvp_responses', 1);
        $this->assertSame(0, $guests[2]->fresh()->rsvp_version);
        $input = $this->payload(app(PersonalRsvp::class)->read($token), [0 => 'attending']);
        $input['responses'][0]['version'] = 500;
        $this->rejected(fn () => app(PersonalRsvp::class)->submit($token, $input));
        $this->assertSame(1, $guests[0]->fresh()->rsvp_version);
    }

    #[DataProvider('invalidSubmissions')]
    public function test_invalid_payloads_reject_all_writes_without_reflecting_private_input(string $mutation): void
    {
        [$owner, $wedding, $group, $guests] = $this->fixture();
        $token = $this->act($owner, $wedding, $group, $guests[0])['token'];
        $input = $this->payload(app(PersonalRsvp::class)->read($token), [0 => 'attending']);
        match ($mutation) {
            'duplicate' => $input['responses'][] = $input['responses'][0],
            'unknown' => $input['responses'][0]['guest'] = str_repeat('f', 64),
            'numeric' => $input['responses'][0]['guest'] = $guests[0]->id,
            'status' => $input['responses'][0]['status'] = '<script>private</script>',
            'pending' => $input['responses'][0]['status'] = 'pending',
            'extra' => $input['account_id'] = 999,
            'nested' => $input['responses'][0]['guest_id'] = $guests[1]->id,
            'missing' => $input['responses'][0] = ['guest' => $input['responses'][0]['guest'], 'status' => 'attending'],
            'authority' => $input['access_version'] = 0,
            'empty' => $input['responses'] = [],
        };
        $error = $this->rejected(fn () => app(PersonalRsvp::class)->submit($token, $input));
        $this->assertStringNotContainsString($token, $error->getMessage());
        $this->assertStringNotContainsString('<script>', $error->getMessage());
        $this->assertDatabaseCount('rsvp_responses', 0);
    }

    public static function invalidSubmissions(): array
    {
        return array_map(fn (string $value): array => [$value], ['duplicate', 'unknown', 'numeric', 'status', 'pending', 'extra', 'nested', 'missing', 'authority', 'empty']);
    }

    public function test_even_valid_hmac_references_cannot_authorize_other_independent_or_foreign_guests(): void
    {
        [$owner, $wedding, $group, $guests] = $this->fixture();
        $token = $this->act($owner, $wedding, $group, $guests[0])['token'];
        [, , , $foreign] = $this->fixture();
        $other = InvitationGroup::factory()->for($wedding)->withGuests()->create()->guests->sole();
        $state = app(PersonalRsvp::class)->read($token);
        foreach ([$guests[1], $other, $foreign[0]] as $forbidden) {
            $input = $this->payload($state, [0 => 'attending']);
            $input['responses'][] = ['guest' => hash_hmac('sha256', 'personal-rsvp:'.$forbidden->id, $token), 'version' => 0, 'status' => 'attending'];
            $this->rejected(fn () => app(PersonalRsvp::class)->submit($token, $input));
        }
        $this->assertDatabaseCount('rsvp_responses', 0);
    }

    public function test_revoked_rotated_unpublished_and_unsupported_invitation_state_rejects_reads_and_writes(): void
    {
        [$owner, $wedding, $group, $guests] = $this->fixture();
        $token = $this->act($owner, $wedding, $group, $guests[0])['token'];
        $input = $this->payload(app(PersonalRsvp::class)->read($token), [0 => 'attending']);
        $next = $this->act($owner, $wedding, $group, $guests[0], 'regenerate')['token'];
        $this->unavailable(fn () => app(PersonalRsvp::class)->submit($token, $input));
        $this->act($owner, $wedding, $group, $guests[0], 'revoke');
        $this->unavailable(fn () => app(PersonalRsvp::class)->submit($next, $input));
        $current = $this->act($owner, $wedding, $group, $guests[0], 'regenerate')['token'];
        DB::table('website_revisions')->where('id', $wedding->fresh()->published_revision_id)->update(['schema_version' => 500]);
        $this->unavailable(fn () => app(PersonalRsvp::class)->read($current));
        $this->unavailable(fn () => app(PersonalRsvp::class)->submit($current, $input));
        $wedding->published_revision_id = null;
        $wedding->save();
        $this->unavailable(fn () => app(PersonalRsvp::class)->read($current));
        foreach (['', 'malformed', str_repeat('f', 64), strtoupper($current)] as $invalid) {
            $this->unavailable(fn () => app(PersonalRsvp::class)->submit($invalid, $input));
        }
        $this->assertDatabaseCount('rsvp_responses', 0);
    }

    #[DataProvider('inFlightChanges')]
    public function test_credentials_delegations_and_publication_are_revalidated_after_candidate_lookup(string $change): void
    {
        [$owner, $wedding, $group, $guests] = $this->fixture();
        $token = $this->act($owner, $wedding, $group, $guests[0])['token'];
        $this->act($owner, $wedding, $group, $guests[1]);
        $this->delegate($owner, $wedding, $group, $guests[2], $guests[0]);
        $input = $this->payload(app(PersonalRsvp::class)->read($token), [0 => 'attending', 1 => 'declined']);
        $transactions = Mockery::mock(PersonalRsvpTransaction::class)->makePartial();
        $transactions->shouldReceive('run')->once()->andReturnUsing(function ($weddingId, $groupId, $operation) use ($owner, $wedding, $group, $guests, $change) {
            if ($change === 'delegation') {
                $this->delegate($owner, $wedding, $group, $guests[2], $guests[1]);
            } elseif ($change === 'unpublish') {
                $wedding->published_revision_id = null;
                $wedding->save();
            } else {
                $this->act($owner, $wedding, $group, $guests[0], $change);
            }

            return (new PersonalRsvpTransaction)->run($weddingId, $groupId, $operation);
        });
        $submit = fn () => (new PersonalRsvp($transactions, app(PublishedWedding::class)))->submit($token, $input);
        if ($change === 'delegation') {
            $this->rejected($submit);
        } else {
            $this->unavailable($submit);
        }
        $this->assertDatabaseCount('rsvp_responses', 0);
    }

    public static function inFlightChanges(): array
    {
        return array_map(fn (string $value): array => [$value], ['revoke', 'regenerate', 'delegation', 'unpublish']);
    }

    public function test_mid_designation_and_revocation_failures_restore_tokens_permissions_and_versions(): void
    {
        [$owner, $wedding, $group, $guests] = $this->fixture();
        $holder = $this->act($owner, $wedding, $group, $guests[0])['token'];
        $dependentToken = $this->act($owner, $wedding, $group, $guests[2])['token'];
        RsvpDelegation::creating(function (): void {
            throw new RuntimeException('Synthetic designation failure');
        });
        $this->rejected(fn () => $this->delegate($owner, $wedding, $group, $guests[2], $guests[0]), RuntimeException::class);
        $this->assertCount(1, app(PersonalRsvp::class)->read($dependentToken)['guests']);
        $this->assertSame(1, $guests[2]->fresh()->rsvp_access_version);
        $this->assertDatabaseCount('rsvp_delegations', 0);
        RsvpDelegation::flushEventListeners();
        $this->delegate($owner, $wedding, $group, $guests[2], $guests[0]);
        $this->delegate($owner, $wedding, $group, $guests[3], $guests[0]);
        $versions = $this->versions(...$guests);
        RsvpDelegation::updating(function (RsvpDelegation $slot) use ($guests): void {
            if ($slot->dependent_guest_id === $guests[3]->id && $slot->representative_guest_id === null) {
                throw new RuntimeException('Synthetic revocation failure');
            }
        });
        $this->rejected(fn () => $this->act($owner, $wedding, $group, $guests[0], 'revoke'), RuntimeException::class);
        $this->assertCount(3, app(PersonalRsvp::class)->read($holder)['guests']);
        $this->assertSame($versions, $this->versions(...$guests));
        $this->assertSame(2, RsvpDelegation::where('representative_guest_id', $guests[0]->id)->count());
    }

    public function test_counter_exhaustion_rolls_back_first_issuance_instead_of_wrapping(): void
    {
        [$owner, $wedding, $group, $guests] = $this->fixture();
        DB::table('guests')->where('id', $guests[0]->id)->update(['rsvp_access_version' => PersonalRsvpTransaction::MAX_VERSION]);
        $this->rejected(fn () => $this->act($owner, $wedding, $group, $guests[0]));
        $this->assertDatabaseCount('guest_invitation_links', 0);
        $this->assertSame(PersonalRsvpTransaction::MAX_VERSION, $guests[0]->fresh()->rsvp_access_version);
    }

    public function test_stored_foreign_delegations_fail_closed_without_exposing_foreign_names(): void
    {
        [$owner, $wedding, $group, $guests] = $this->fixture();
        $token = $this->act($owner, $wedding, $group, $guests[0])['token'];
        [, , , $foreign] = $this->fixture();
        RsvpDelegation::factory()->for($foreign[2], 'dependent')->create(['representative_guest_id' => $guests[0]->id]);
        $this->unavailable(fn () => app(PersonalRsvp::class)->read($token));
        $this->unavailable(fn () => app(PersonalRsvp::class)->submit($token, []));
        $this->assertDatabaseCount('rsvp_responses', 0);
    }

    public function test_private_draft_edits_and_personal_response_changes_leave_published_snapshot_untouched(): void
    {
        [$owner, $wedding, $group, $guests] = $this->fixture();
        $revision = $wedding->websiteRevisions()->sole()->getAttributes();
        $token = $this->act($owner, $wedding, $group, $guests[0])['token'];
        $wedding->update(['partner_one_name' => 'Private draft-only name']);
        $wedding->websiteDraft()->sole()->update(['content' => [...WebsiteDraft::defaultContent(), 'introduction_message' => 'Private draft-only message']]);
        $state = app(PersonalRsvp::class)->submit($token, $this->payload(app(PersonalRsvp::class)->read($token), [0 => 'attending']));
        $this->assertStringNotContainsString('Private draft-only', json_encode($state));
        $this->assertSame($revision, $wedding->websiteRevisions()->sole()->getAttributes());
    }

    public function test_mid_batch_storage_failure_rolls_back_every_response_version_and_timestamp(): void
    {
        [$owner, $wedding, $group, $guests] = $this->fixture();
        $token = $this->act($owner, $wedding, $group, $guests[0])['token'];
        $this->delegate($owner, $wedding, $group, $guests[2], $guests[0]);
        $input = $this->payload(app(PersonalRsvp::class)->read($token), [0 => 'attending', 1 => 'declined']);
        RsvpResponse::creating(function (RsvpResponse $response) use ($guests): void {
            if ($response->guest_id === $guests[2]->id) {
                throw new RuntimeException('Synthetic second-response failure');
            }
        });
        $this->rejected(fn () => app(PersonalRsvp::class)->submit($token, $input), RuntimeException::class);
        $this->assertDatabaseCount('rsvp_responses', 0);
        $this->assertSame(0, $guests[0]->fresh()->rsvp_version);
        $this->assertSame(0, $guests[2]->fresh()->rsvp_version);
    }

    public function test_only_personal_credentials_are_accepted_on_public_routes(): void
    {
        $this->withoutVite();
        [$owner, $wedding, $group, $guests] = $this->fixture();
        $personal = $this->act($owner, $wedding, $group, $guests[0])['token'];
        $legacy = app(InvitationLink::class)->execute($wedding, $group, 'generate', 0)['url'];
        $this->get($legacy)->assertNotFound();
        $this->postJson($legacy.'/rsvp', [])->assertNotFound();
        $this->get('/rsvp/'.$personal)->assertOk();
        $this->get('/invite/'.$personal)->assertNotFound();
        $this->unavailable(fn () => app(PersonalRsvp::class)->read(basename($legacy)));
    }

    public function test_missing_personal_schema_blocks_management_instead_of_restoring_legacy(): void
    {
        $this->withoutVite();
        [$owner, $wedding, $group] = $this->fixture();
        Schema::drop('guest_invitation_links');
        $base = '/accounts/'.$wedding->account_id.'/weddings/'.$wedding->id;
        $this->actingAs($owner)->get($base.'/guests')->assertStatus(503);
        $this->post($base.'/invitation-groups', ['label' => 'Blocked', 'guests' => [['name' => 'New person']]])->assertStatus(503);
        $this->assertSame(1, $wedding->invitationGroups()->count());
    }

    private function fixture(): array
    {
        $owner = User::factory()->create();
        $account = Account::create(['name' => 'Synthetic personal RSVP workspace']);
        $account->users()->attach($owner, ['role' => 'owner']);
        $wedding = $account->weddings()->create(['partner_one_name' => 'Published Alex', 'partner_two_name' => 'Published Jamie', 'wedding_date' => '2027-05-12', 'public_slug' => fake()->uuid()]);
        $wedding->websiteDraft()->create(['schema_version' => 1, 'lock_version' => 0, 'content' => WebsiteDraft::defaultContent()]);
        $publisher = app(WeddingPublication::class);
        $summary = $publisher->summary($wedding);
        $publisher->publish($wedding, ['draft_version' => 0, 'publication_version' => 0, 'snapshot_token' => $summary['snapshotToken']]);
        $group = InvitationGroup::factory()->for($wedding)->has(Guest::factory()->count(4)->sequence(['name' => 'Juan'], ['name' => 'Maria'], ['name' => 'Miguel'], ['name' => 'Andrea']))->create(['label' => 'Private household', 'contact_email' => 'private@example.test']);

        return [$owner, $wedding->fresh(), $group, $group->guests->all()];
    }

    private function act(User $owner, Wedding $wedding, InvitationGroup $group, Guest $guest, string $action = 'generate'): array
    {
        return app(PersonalInvitationLink::class)->execute($owner, $wedding, $group, $guest, $action, $guest->fresh()->invitationLink?->version ?? 0, $guest->fresh()->rsvp_access_version);
    }

    private function versions(Guest ...$guests): array
    {
        return collect($guests)->mapWithKeys(fn (Guest $guest): array => [$guest->id => $guest->fresh()->rsvp_access_version])->all();
    }

    private function delegate(User $owner, Wedding $wedding, InvitationGroup $group, Guest $dependent, ?Guest $representative): array
    {
        $previous = $dependent->fresh()->rsvpDelegation?->representative;
        $versions = $this->versions(...array_filter([$dependent, $previous, $representative]));
        $linkVersion = $dependent->fresh()->invitationLink?->version ?? 0;

        return $representative === null
            ? app(RsvpDelegationManager::class)->revoke($owner, $wedding, $group, $dependent, $versions, $linkVersion)
            : app(RsvpDelegationManager::class)->assign($owner, $wedding, $group, $dependent, $representative, $versions, $linkVersion);
    }

    private function payload(array $state, array $statuses): array
    {
        $responses = [];
        foreach ($statuses as $index => $status) {
            $responses[] = ['guest' => $state['guests'][$index]['reference'], 'version' => $state['guests'][$index]['version'], 'status' => $status];
        }

        return ['access_version' => $state['access_version'], 'responses' => $responses];
    }

    private function rejected(callable $action, string $expected = ValidationException::class): Throwable
    {
        try {
            $action();
        } catch (Throwable $error) {
            $this->assertInstanceOf($expected, $error);

            return $error;
        }
        $this->fail('The operation should have been rejected.');
    }

    private function unavailable(callable $action): void
    {
        try {
            $action();
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);

            return;
        } catch (HttpExceptionInterface $error) {
            $this->assertSame(404, $error->getStatusCode());

            return;
        }
        $this->fail('Personal access should be unavailable.');
    }
}
