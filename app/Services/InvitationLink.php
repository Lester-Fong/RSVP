<?php

namespace App\Services;

use App\Models\InvitationGroup;
use App\Models\Wedding;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvitationLink
{
    public const HEADERS = [
        'Cache-Control' => 'private, no-store',
        'Referrer-Policy' => 'no-referrer',
        'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        'X-Content-Type-Options' => 'nosniff',
    ];

    /** @return array{status: string, version: int, url: ?string} */
    public function execute(Wedding $wedding, InvitationGroup $group, string $action, int $expectedVersion): array
    {
        return DB::transaction(function () use ($wedding, $group, $action, $expectedVersion): array {
            $locked = $wedding->invitationGroups()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            if ($locked->invitation_link_version !== $expectedVersion) {
                $this->fail('This invitation link changed in another window. Reload the guest list before trying again.');
            }
            $status = $locked->linkStatus();
            if ($action === 'copy' && $status !== 'active') {
                $this->fail('There is no active link to copy.');
            }
            if ($action === 'generate' && $status === 'revoked') {
                $this->fail('This link was revoked. Choose Regenerate to create a new link.');
            }
            if (in_array($action, ['revoke', 'regenerate'], true) && $status === 'not_generated') {
                $this->fail('Generate an invitation link first.');
            }
            if (($action === 'generate' && $status === 'not_generated') || $action === 'regenerate') {
                $token = bin2hex(random_bytes(32));
                $locked->invitation_token_hash = hash('sha256', $token);
                $locked->invitation_token = $token;
                $locked->invitation_link_version++;
                $locked->save();
            } elseif ($action === 'revoke' && $status === 'active') {
                $locked->invitation_token_hash = null;
                $locked->invitation_token = null;
                $locked->invitation_link_version++;
                $locked->save();
            }

            return [
                'status' => $locked->linkStatus(), 'version' => $locked->invitation_link_version,
                'url' => $action === 'revoke' ? null : $this->recover($locked),
            ];
        });
    }

    private function recover(InvitationGroup $group): string
    {
        try {
            $token = $group->invitation_token;
        } catch (DecryptException) {
            $this->fail('This link could not be recovered. Regenerate it to create a new one.');
        }
        if (! is_string($token) || ! preg_match('/\A[a-f0-9]{64}\z/D', $token)
            || ! hash_equals($group->invitation_token_hash ?? '', hash('sha256', $token))) {
            $this->fail('This link could not be recovered. Regenerate it to create a new one.');
        }

        return route('public.invitations.show', ['token' => $token]);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['invitation_link' => $message]);
    }
}
