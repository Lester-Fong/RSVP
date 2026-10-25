<?php

namespace App\Services;

use App\Exceptions\PersonalRsvpValidationException;
use App\Models\Guest;
use App\Models\GuestInvitationLink;
use App\Models\InvitationGroup;
use App\Models\RsvpDelegation;
use App\Models\RsvpResponse;
use App\Models\User;
use App\Models\Wedding;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PersonalRsvpTransaction
{
    public const MAX_VERSION = 4294967295;

    /** Shared wedding-first transaction for personal RSVP and guest identity/retention writes. */
    public function run(int $weddingId, int $groupId, Closure $operation, ?User $owner = null): mixed
    {
        $this->assertEligibilitySchema();

        return DB::transaction(function () use ($weddingId, $groupId, $operation, $owner): mixed {
            $wedding = Wedding::query()->whereKey($weddingId)->lockForUpdate()->firstOrFail();
            if ($owner !== null) {
                Gate::forUser($owner)->authorize('update', $wedding);
            }
            $group = $wedding->invitationGroups()->whereKey($groupId)->lockForUpdate()->firstOrFail();
            $guests = $group->guests()->orderBy('id')->lockForUpdate()->get();
            $ids = $guests->modelKeys();
            $links = GuestInvitationLink::query()->whereIn('guest_id', $ids)->orderBy('guest_id')->lockForUpdate()->get()->keyBy('guest_id');
            $delegations = RsvpDelegation::query()->where(fn (Builder $query): Builder => $query->whereIn('dependent_guest_id', $ids)->orWhereIn('representative_guest_id', $ids))
                ->orderBy('dependent_guest_id')->lockForUpdate()->get();
            $responses = RsvpResponse::query()->whereIn('guest_id', $ids)->orderBy('guest_id')->lockForUpdate()->get()->keyBy('guest_id');
            foreach ($guests as $guest) {
                $guest->setRelation('invitationLink', $links->get($guest->id));
                $guest->setRelation('rsvpDelegation', $delegations->firstWhere('dependent_guest_id', $guest->id));
                $guest->setRelation('representedDelegations', $delegations->where('representative_guest_id', $guest->id)->values());
                $guest->setRelation('rsvpResponse', $responses->get($guest->id));
            }
            $group->setRelation('guests', $guests);
            $group->setRelation('wedding', $wedding);

            return $operation($wedding, $group);
        }, 3);
    }

    public function member(InvitationGroup $group, int $guestId): Guest
    {
        return $group->guests->firstWhere('id', $guestId) ?? abort(404);
    }

    public function hasActiveCredential(Guest $guest): bool
    {
        if (! $this->eligible($guest)) {
            return false;
        }
        $link = $guest->invitationLink;

        return $link !== null && $link->version > 0
            && is_string($link->token_hash) && preg_match('/\A[a-f0-9]{64}\z/D', $link->token_hash) === 1
            && is_string($link->getRawOriginal('token')) && $link->getRawOriginal('token') !== '';
    }

    public function isIndependent(Guest $guest): bool
    {
        return $guest->rsvpDelegation?->representative_guest_id === null;
    }

    /** Explicit internal context; no schema probing or active fallback when enabled. */
    public function assertEligibilitySchema(): void
    {
        if (app(InvitationEligibility::class)->enabled) {
            abort_unless(app(RsvpMode::class)->personal(), 503);
            app(RsvpMode::class)->assertSchema();
        }
    }

    public function eligible(Guest $guest): bool
    {
        $eligibility = app(InvitationEligibility::class);

        return ! $eligibility->enabled || $eligibility->active($guest);
    }

    public function requireEligible(Guest $guest): void
    {
        if (! $this->eligible($guest)) {
            $this->fail('This invitation is withdrawn. Reinstate it before arranging RSVP access.');
        }
    }

    public function status(Guest $guest): string
    {
        return $guest->invitationLink === null ? 'not_generated' : ($this->hasActiveCredential($guest) ? 'active' : 'revoked');
    }

    public function expectAccess(Guest $guest, int $expected): void
    {
        if ($expected !== $guest->rsvp_access_version) {
            $this->fail('RSVP access changed in another window. Reload and review the current permissions.', 'stale_access');
        }
    }

    public function nextVersion(int $version): int
    {
        if ($version < 0 || $version >= self::MAX_VERSION) {
            $this->fail('This record cannot be updated safely. Please contact support.');
        }

        return $version + 1;
    }

    /** @param array<int, Guest> $guests */
    public function bumpAccess(array $guests): void
    {
        $unique = [];
        foreach ($guests as $guest) {
            $unique[$guest->id] = $guest;
        }
        ksort($unique);
        foreach ($unique as $guest) {
            $guest->rsvp_access_version = $this->nextVersion($guest->rsvp_access_version);
            $guest->save();
        }
    }

    public function clearCredential(Guest $guest): bool
    {
        $link = $guest->invitationLink;
        if ($link === null || ($link->token_hash === null && $link->getRawOriginal('token') === null)) {
            return false;
        }
        $link->token_hash = null;
        $link->token = null;
        $link->version = $this->nextVersion($link->version);
        $link->save();

        return true;
    }

    public function fail(string $message, string $kind = 'validation'): never
    {
        throw PersonalRsvpValidationException::because('rsvp_access', $message, $kind);
    }
}
