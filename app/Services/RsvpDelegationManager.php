<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\InvitationGroup;
use App\Models\RsvpDelegation;
use App\Models\User;
use App\Models\Wedding;

class RsvpDelegationManager
{
    public function __construct(private PersonalRsvpTransaction $transactions) {}

    public function inspect(User $owner, Wedding $wedding, InvitationGroup $group, Guest $guest): array
    {
        return $this->transactions->run($wedding->id, $group->id, fn (Wedding $locked, InvitationGroup $members): array => $this->state($this->transactions->member($members, $guest->id), $members), $owner);
    }

    /** @param array<int, int> $expectedAccessVersions */
    public function assign(User $owner, Wedding $wedding, InvitationGroup $group, Guest $dependent, Guest $representative, array $expectedAccessVersions, int $expectedDependentLinkVersion): array
    {
        return $this->change($owner, $wedding, $group, $dependent, $representative, $expectedAccessVersions, $expectedDependentLinkVersion);
    }

    /** @param array<int, int> $expectedAccessVersions */
    public function revoke(User $owner, Wedding $wedding, InvitationGroup $group, Guest $dependent, array $expectedAccessVersions, int $expectedDependentLinkVersion): array
    {
        return $this->change($owner, $wedding, $group, $dependent, null, $expectedAccessVersions, $expectedDependentLinkVersion);
    }

    private function change(User $owner, Wedding $wedding, InvitationGroup $group, Guest $dependent, ?Guest $representative, array $expectedAccessVersions, int $expectedDependentLinkVersion): array
    {
        return $this->transactions->run($wedding->id, $group->id, function (Wedding $locked, InvitationGroup $members) use ($dependent, $representative, $expectedAccessVersions, $expectedDependentLinkVersion): array {
            $dependent = $this->transactions->member($members, $dependent->id);
            $representative = $representative === null ? null : $this->transactions->member($members, $representative->id);
            $slot = $dependent->rsvpDelegation;
            $previous = $slot?->representative_guest_id === null ? null : $this->transactions->member($members, $slot->representative_guest_id);
            $affected = collect([$dependent, $previous, $representative])->filter()->keyBy('id')->all();
            $keys = array_keys($expectedAccessVersions);
            $required = array_keys($affected);
            sort($keys, SORT_NUMERIC);
            sort($required, SORT_NUMERIC);
            if ($keys !== $required || ($dependent->invitationLink?->version ?? 0) !== $expectedDependentLinkVersion) {
                $this->transactions->fail('The affected people or personal link changed. Reload and review the current permissions.', 'stale_access');
            }
            foreach ($affected as $guest) {
                if (! is_int($expectedAccessVersions[$guest->id])) {
                    $this->transactions->fail('Use the current RSVP access versions for every affected person.');
                }
                $this->transactions->expectAccess($guest, $expectedAccessVersions[$guest->id]);
            }
            if ($representative !== null) {
                $this->transactions->requireEligible($dependent);
                $this->transactions->requireEligible($representative);
            }
            if ($representative !== null && ($dependent->id === $representative->id
                || ! $this->transactions->isIndependent($representative) || ! $this->transactions->hasActiveCredential($representative)
                || $dependent->representedDelegations->isNotEmpty())) {
                $this->transactions->fail('Choose an independent same-group representative with active personal access. A representative with dependents cannot become a dependent.');
            }
            $credentialChanged = ($representative !== null || $previous !== null) && $this->transactions->clearCredential($dependent);
            $designationChanged = ($slot?->representative_guest_id) !== $representative?->id;
            if ($designationChanged) {
                $slot ??= new RsvpDelegation;
                $slot->dependent_guest_id = $dependent->id;
                $slot->representative_guest_id = $representative?->id;
                $slot->save();
                $dependent->setRelation('rsvpDelegation', $slot);
            }
            if ($designationChanged || $credentialChanged) {
                $this->transactions->bumpAccess(array_values($affected));
            }

            return $this->state($dependent, $members);
        }, $owner);
    }

    private function state(Guest $guest, InvitationGroup $group): array
    {
        $representative = $guest->rsvpDelegation?->representative_guest_id;

        return ['guest' => ['id' => $guest->id, 'name' => $guest->name], 'access_version' => $guest->rsvp_access_version,
            'link_version' => $guest->invitationLink?->version ?? 0, 'link_status' => $this->transactions->status($guest),
            'mode' => $representative === null ? 'independent' : 'represented',
            'representative' => $representative === null ? null : $this->person($this->transactions->member($group, $representative)),
            'dependents' => $guest->representedDelegations->map(fn (RsvpDelegation $slot): array => $this->person($this->transactions->member($group, $slot->dependent_guest_id)))->all()];
    }

    private function person(Guest $guest): array
    {
        return ['id' => $guest->id, 'name' => $guest->name];
    }
}
