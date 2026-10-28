<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\InvitationGroup;
use App\Models\User;
use App\Models\Wedding;
use Closure;
use Illuminate\Support\Facades\Validator;

/**
 * @phpstan-type Confirmation array{expectedState: 'active'|'withdrawn', expectedGuests: list<array{guestId: int, accessVersion: int, responseVersion: int, linkVersion: int}>}
 * @phpstan-type OwnerState array{guest: array{id: int, name: string}, state: 'active'|'withdrawn', withdrawnAt: ?string, status: string, respondedAt: ?string}
 */
class GuestInvitationLifecycle
{
    public function __construct(private PersonalRsvpTransaction $transactions, private InvitationEligibility $eligibility) {}

    /** Return token-free owner confirmation state for the current locked affected set. */
    public function inspect(User $owner, Wedding $wedding, InvitationGroup $group, Guest $guest, string $action): array
    {
        return $this->run($owner, $wedding, $group, function (InvitationGroup $members) use ($guest, $action): array {
            $this->validateAction($action);
            $target = $this->transactions->member($members, $guest->id);
            $affected = $this->affected($members, $target, $action);

            return [...$this->state($target), 'action' => $action,
                'affected' => array_map(fn (Guest $person): array => ['id' => $person->id, 'name' => $person->name], array_values($affected)),
                'confirmation' => ['expectedState' => $this->eligibility->active($target) ? 'active' : 'withdrawn',
                    'expectedGuests' => array_map(fn (Guest $person): array => $this->versions($person), array_values($affected))]];
        });
    }

    /** @param Confirmation $input */
    public function withdraw(User $owner, Wedding $wedding, InvitationGroup $group, Guest $guest, array $input): array
    {
        return $this->change($owner, $wedding, $group, $guest, 'withdraw', $input);
    }

    /** @param Confirmation $input */
    public function reinstate(User $owner, Wedding $wedding, InvitationGroup $group, Guest $guest, array $input): array
    {
        return $this->change($owner, $wedding, $group, $guest, 'reinstate', $input);
    }

    private function run(User $owner, Wedding $wedding, InvitationGroup $group, Closure $operation): array
    {
        abort_unless(app(InvitationEligibility::class)->enabled, 503);
        $this->eligibility->assertSchema();

        return $this->transactions->run($wedding->id, $group->id, fn (Wedding $locked, InvitationGroup $members): array => $operation($members), $owner);
    }

    private function change(User $owner, Wedding $wedding, InvitationGroup $group, Guest $guest, string $action, array $input): array
    {
        return $this->run($owner, $wedding, $group, function (InvitationGroup $members) use ($guest, $action, $input): array {
            $target = $this->transactions->member($members, $guest->id);
            $affected = $this->affected($members, $target, $action);
            $this->expectConfirmation($target, $affected, $input);
            $withdraw = $action === 'withdraw';
            if ($withdraw === ! $this->eligibility->active($target)) {
                return [...$this->state($target), 'changed' => false];
            }

            foreach ($affected as $person) {
                $this->transactions->nextVersion($person->rsvp_access_version);
            }
            if ($withdraw && $this->hasCredentialMaterial($target)) {
                $this->transactions->nextVersion($target->invitationLink->version);
            }

            $target->withdrawn_at = $withdraw ? now() : null;
            if ($withdraw) {
                $this->transactions->clearCredential($target);
                if ($target->rsvpDelegation?->representative_guest_id !== null) {
                    $target->rsvpDelegation->representative_guest_id = null;
                    $target->rsvpDelegation->save();
                }
                foreach ($target->representedDelegations as $slot) {
                    $slot->representative_guest_id = null;
                    $slot->save();
                }
                $target->setRelation('representedDelegations', collect());
            }
            $this->transactions->bumpAccess(array_values($affected));

            return [...$this->state($target), 'changed' => true];
        });
    }

    /**
     * Revalidate the affected authority graph from the fully locked retained members.
     *
     * @return array<int, Guest>
     */
    private function affected(InvitationGroup $group, Guest $target, string $action): array
    {
        $active = $this->eligibility->active($target);
        $incoming = $target->rsvpDelegation?->representative_guest_id;
        $outgoing = $target->representedDelegations;
        if (! $active && ($incoming !== null || $outgoing->isNotEmpty() || $this->hasCredentialMaterial($target))) {
            $this->transactions->fail('This invitation has inconsistent access. Please contact support.');
        }
        $affected = [$target->id => $target];
        if ($incoming !== null) {
            $representative = $this->transactions->member($group, $incoming);
            if ($incoming === $target->id || ! $this->eligibility->active($representative)
                || ! $this->transactions->isIndependent($representative) || ! $this->transactions->hasActiveCredential($representative)
                || $this->transactions->hasActiveCredential($target) || $outgoing->isNotEmpty()) {
                $this->transactions->fail('This invitation has inconsistent representation. Please contact support.');
            }
            $affected[$incoming] = $representative;
        }
        foreach ($outgoing as $slot) {
            $dependent = $this->transactions->member($group, $slot->dependent_guest_id);
            if ($dependent->id === $target->id || ! $this->eligibility->active($dependent)
                || $dependent->rsvpDelegation?->representative_guest_id !== $target->id
                || ! $this->transactions->isIndependent($target) || ! $this->transactions->hasActiveCredential($target)
                || $this->transactions->hasActiveCredential($dependent) || $dependent->representedDelegations->isNotEmpty()) {
                $this->transactions->fail('This invitation has inconsistent representation. Please contact support.');
            }
            $affected[$dependent->id] = $dependent;
        }
        if ($action === 'reinstate') {
            return [$target->id => $target];
        }
        ksort($affected, SORT_NUMERIC);

        return $affected;
    }

    private function expectConfirmation(Guest $target, array $affected, array $input): void
    {
        $maximum = PersonalRsvpTransaction::MAX_VERSION;
        $validator = Validator::make($input, [
            'expectedState' => ['required', 'string', 'in:active,withdrawn'],
            'expectedGuests' => ['required', 'array', 'list', 'min:1', 'max:'.InvitationGroup::MAX_GUESTS],
            'expectedGuests.*' => ['required', 'array:guestId,accessVersion,responseVersion,linkVersion'],
            'expectedGuests.*.guestId' => ['required', 'integer', 'min:1', 'distinct:strict'],
            'expectedGuests.*.accessVersion' => ['required', 'integer', 'min:0', 'max:'.$maximum],
            'expectedGuests.*.responseVersion' => ['required', 'integer', 'min:0', 'max:'.$maximum],
            'expectedGuests.*.linkVersion' => ['required', 'integer', 'min:0', 'max:'.$maximum],
        ]);
        if (array_diff(array_keys($input), ['expectedState', 'expectedGuests']) !== [] || $validator->fails()) {
            $this->transactions->fail('Review the current invitation details before confirming this change.');
        }
        $submitted = [];
        foreach ($input['expectedGuests'] as $entry) {
            foreach ($entry as $value) {
                if (! is_int($value)) {
                    $this->transactions->fail('Use the current invitation details and versions.');
                }
            }
            $submitted[$entry['guestId']] = $entry;
        }
        ksort($submitted, SORT_NUMERIC);
        $current = [];
        foreach ($affected as $person) {
            $current[$person->id] = $this->versions($person);
        }
        ksort($current, SORT_NUMERIC);
        if ($input['expectedState'] !== ($this->eligibility->active($target) ? 'active' : 'withdrawn')
            || array_keys($submitted) !== array_keys($current)) {
            $this->stale();
        }
        foreach ($current as $id => $versions) {
            foreach ($versions as $key => $value) {
                if ($submitted[$id][$key] !== $value) {
                    $this->stale();
                }
            }
        }
    }

    private function versions(Guest $guest): array
    {
        return ['guestId' => $guest->id, 'accessVersion' => $guest->rsvp_access_version,
            'responseVersion' => $guest->rsvp_version, 'linkVersion' => $guest->invitationLink?->version ?? 0];
    }

    /** @return OwnerState */
    private function state(Guest $guest): array
    {
        return ['guest' => ['id' => $guest->id, 'name' => $guest->name],
            'state' => $this->eligibility->active($guest) ? 'active' : 'withdrawn',
            'withdrawnAt' => $guest->withdrawn_at?->toIso8601String(),
            'status' => $guest->rsvpResponse?->status ?? 'pending',
            'respondedAt' => $guest->rsvpResponse?->responded_at->toIso8601String()];
    }

    private function hasCredentialMaterial(Guest $guest): bool
    {
        return $guest->invitationLink !== null && ($guest->invitationLink->token_hash !== null || $guest->invitationLink->getRawOriginal('token') !== null);
    }

    private function validateAction(string $action): void
    {
        if (! in_array($action, ['withdraw', 'reinstate'], true)) {
            $this->transactions->fail('This invitation action is unavailable.');
        }
    }

    private function stale(): never
    {
        $this->transactions->fail('Invitation details changed. Review the latest information and confirm again.', 'stale_access');
    }
}
