<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\InvitationGroup;
use App\Models\User;
use App\Models\Wedding;

class PersonalRsvpAccess
{
    public function __construct(private PersonalRsvpTransaction $transactions, private RsvpDelegationManager $delegations, private PersonalInvitationLink $links, private RsvpMode $mode) {}

    public function read(User $owner, Wedding $wedding, InvitationGroup $group): array
    {
        return $this->transactions->run($wedding->id, $group->id, fn (Wedding $locked, InvitationGroup $members): array => $this->projection($members), $owner);
    }

    public function credential(User $owner, Wedding $wedding, InvitationGroup $group, Guest $guest, string $action, array $input): array
    {
        return $this->transactions->run($wedding->id, $group->id, function (Wedding $locked, InvitationGroup $members) use ($owner, $guest, $action, $input): array {
            $this->mode->trustedUrl('/');
            $result = $this->links->execute($owner, $locked, $members, $this->transactions->member($members, $guest->id), $action, $input['expectedVersion'], $input['expectedAccessVersion']);

            return ['group' => $this->read($owner, $locked, $members), 'url' => $result['token'] === null ? null : $this->mode->invitationUrl($result['token'])];
        }, $owner);
    }

    public function delegate(User $owner, Wedding $wedding, InvitationGroup $group, Guest $guest, array $input): array
    {
        return $this->transactions->run($wedding->id, $group->id, function (Wedding $locked, InvitationGroup $members) use ($owner, $guest, $input): array {
            $dependent = $this->transactions->member($members, $guest->id);
            $versions = array_map(intval(...), $input['expectedAccessVersions']);
            if ($input['representativeId'] === null) {
                $this->delegations->revoke($owner, $locked, $members, $dependent, $versions, $input['expectedDependentLinkVersion']);
            } else {
                $representative = $this->transactions->member($members, $input['representativeId']);
                $this->delegations->assign($owner, $locked, $members, $dependent, $representative, $versions, $input['expectedDependentLinkVersion']);
            }

            return ['group' => $this->read($owner, $locked, $members)];
        }, $owner);
    }

    private function projection(InvitationGroup $group): array
    {
        return ['id' => $group->id, 'label' => $group->label, 'members' => $group->guests->map(fn (Guest $guest): array => [
            'groupId' => $group->id, 'guest' => ['id' => $guest->id, 'name' => $guest->name],
            'access_version' => $guest->rsvp_access_version, 'link_version' => $guest->invitationLink?->version ?? 0,
            'link_status' => $this->transactions->status($guest),
            ...(app(InvitationEligibility::class)->enabled ? ['invitation_state' => app(InvitationEligibility::class)->active($guest) ? 'active' : 'withdrawn'] : []),
            'mode' => $this->transactions->isIndependent($guest) ? 'independent' : 'represented',
            'representative' => $guest->rsvpDelegation?->representative_guest_id === null ? null : $this->person($group, $guest->rsvpDelegation->representative_guest_id),
            'dependents' => $guest->representedDelegations->map(fn ($slot): array => $this->person($group, $slot->dependent_guest_id))->all(),
        ])->all()];
    }

    private function person(InvitationGroup $group, int $id): array
    {
        $guest = $this->transactions->member($group, $id);

        return ['id' => $guest->id, 'name' => $guest->name];
    }
}
