<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;
use SensitiveParameter;

class RsvpMode
{
    public function mode(): string
    {
        $mode = config('rsvp.mode', 'personal');

        return $mode === 'personal' ? 'personal' : 'disabled';
    }

    public function personal(): bool
    {
        return $this->mode() === 'personal';
    }

    /** An explicit readiness assertion; missing schema never restores group authority. */
    public function assertSchema(): void
    {
        abort_unless(Schema::hasColumns('guests', ['rsvp_version', 'rsvp_access_version'])
            && Schema::hasColumns('guest_invitation_links', ['id', 'guest_id', 'token_hash', 'token', 'version', 'created_at', 'updated_at'])
            && Schema::hasColumns('rsvp_delegations', ['id', 'dependent_guest_id', 'representative_guest_id', 'created_at', 'updated_at']), 503);
        if (app(InvitationEligibility::class)->enabled) {
            app(InvitationEligibility::class)->assertSchema();
        }
    }

    public function trustedUrl(string $path): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $parts = parse_url($base);
        abort_unless($parts && in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            && isset($parts['host']) && ! isset($parts['user']) && ! isset($parts['pass'])
            && ! isset($parts['query']) && ! isset($parts['fragment']), 503);

        return $base.'/'.ltrim($path, '/');
    }

    public function invitationUrl(#[SensitiveParameter] string $token): string
    {
        return $this->trustedUrl(route('public.personal-rsvp.show', ['token' => $token], absolute: false));
    }
}
