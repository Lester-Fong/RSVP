<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\GuestInvitationLink;
use App\Models\InvitationGroup;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Contracts\Encryption\DecryptException;

class PersonalInvitationLink
{
    public function __construct(private PersonalRsvpTransaction $transactions, private PublishedWedding $published) {}

    /** Explicit owner action only; never serialize this return value into ordinary page props or logs.
     * @return array{status: string, version: int, access_version: int, token: ?string}
     */
    public function execute(User $owner, Wedding $wedding, InvitationGroup $group, Guest $guest, string $action, int $expectedVersion, int $expectedAccessVersion): array
    {
        return $this->transactions->run($wedding->id, $group->id, function (Wedding $lockedWedding, InvitationGroup $lockedGroup) use ($guest, $action, $expectedVersion, $expectedAccessVersion): array {
            if ($action !== 'revoke' && $lockedWedding->websiteRevisions()->whereKey($lockedWedding->published_revision_id)->value('schema_version') === 2) {
                $this->published->revision($lockedWedding, personal: true);
            }
            $member = $this->transactions->member($lockedGroup, $guest->id);
            $link = $member->invitationLink;
            $this->transactions->expectAccess($member, $expectedAccessVersion);
            if (! in_array($action, ['generate', 'copy', 'revoke', 'regenerate'], true)) {
                $this->transactions->fail('This personal invitation link changed or the action is unsupported. Reload before trying again.');
            }
            if (($link?->version ?? 0) !== $expectedVersion) {
                $this->transactions->fail('This personal invitation link changed. Reload before trying again.', 'stale_access');
            }
            if ($action !== 'revoke') {
                $this->transactions->requireEligible($member);
            }
            if ($action !== 'revoke' && ! $this->transactions->isIndependent($member)) {
                $this->transactions->fail('Remove this guest\'s representative designation before issuing independent RSVP access.');
            }
            $status = $this->transactions->status($member);
            if (($action === 'copy' && $status !== 'active') || ($action === 'generate' && $status === 'revoked')
                || (in_array($action, ['revoke', 'regenerate'], true) && $status === 'not_generated')) {
                $this->transactions->fail('This action is unavailable for the current link state. Generate a new link or explicitly regenerate a revoked link.');
            }
            if (($action === 'generate' && $status === 'not_generated') || $action === 'regenerate') {
                $token = bin2hex(random_bytes(32));
                $link ??= new GuestInvitationLink;
                $link->guest_id = $member->id;
                $link->token_hash = hash('sha256', $token);
                $link->token = $token;
                $link->version = $link->exists ? $this->transactions->nextVersion($link->version) : 1;
                $link->save();
                $member->setRelation('invitationLink', $link);
                $this->transactions->bumpAccess([$member]);
            } elseif ($action === 'revoke') {
                $affected = [];
                if ($this->transactions->clearCredential($member)) {
                    $affected[] = $member;
                }
                foreach ($member->representedDelegations as $delegation) {
                    $dependent = $this->transactions->member($lockedGroup, $delegation->dependent_guest_id);
                    $this->transactions->clearCredential($dependent);
                    $delegation->representative_guest_id = null;
                    $delegation->save();
                    $affected[] = $dependent;
                    $affected[] = $member;
                }
                $this->transactions->bumpAccess($affected);
            }

            return ['status' => $this->transactions->status($member), 'version' => $member->invitationLink->version,
                'access_version' => $member->rsvp_access_version, 'token' => $action === 'revoke' ? null : $this->recover($member->invitationLink)];
        }, $owner);
    }

    private function recover(GuestInvitationLink $link): string
    {
        try {
            $token = $link->token;
        } catch (DecryptException) {
            $this->transactions->fail('This personal link could not be recovered. Explicitly regenerate it to create a replacement.');
        }
        if (! is_string($token) || preg_match('/\A[a-f0-9]{64}\z/D', $token) !== 1 || ! hash_equals($link->token_hash ?? '', hash('sha256', $token))) {
            $this->transactions->fail('This personal link could not be recovered. Explicitly regenerate it to create a replacement.');
        }

        return $token;
    }
}
