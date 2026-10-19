<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\InvitationGroup;
use App\Models\Wedding;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvitationRsvp
{
    public function __construct(private PublishedWedding $published) {}

    public function assertAvailable(string $token): void
    {
        $group = InvitationGroup::query()->where('invitation_token_hash', hash('sha256', $token))->firstOrFail();
        abort_unless($group->linkStatus() === 'active', 404);
        $this->published->revision($group->wedding);
    }

    /** @return array{wedding: Wedding, greeting: array, rsvp: array} */
    public function read(string $token): array
    {
        return $this->withInvitation($token, fn (Wedding $wedding, InvitationGroup $group): array => [
            'wedding' => $wedding,
            'greeting' => ['label' => $group->label, 'guests' => $group->guests->pluck('name')->all()],
            'rsvp' => $this->presentation($group, $token),
        ]);
    }

    /** @param array{version: int|string, responses: array<int, array{guest: string, status: string}>} $input */
    public function submit(string $token, array $input): array
    {
        return $this->withInvitation($token, function (Wedding $wedding, InvitationGroup $group) use ($token, $input): array {
            $members = $group->guests->keyBy(fn (Guest $guest): string => $this->reference($guest, $token));
            $changes = [];
            foreach ($input['responses'] as $entry) {
                $guest = $members->get($entry['guest']);
                if (! $guest) {
                    throw ValidationException::withMessages(['rsvp' => 'The invited names have changed or a selected person is unavailable. Reload this invitation before trying again.']);
                }
                if ($guest->rsvpResponse?->status !== $entry['status']) {
                    $changes[] = [$guest, $entry['status']];
                }
            }
            $version = (int) $input['version'];
            if ($version > $group->rsvp_version || ($version !== $group->rsvp_version && $changes !== [])) {
                throw ValidationException::withMessages(['rsvp' => 'These responses or invited names changed in another window. Reload this invitation and review the saved choices before trying again.']);
            }
            foreach ($changes as [$guest, $status]) {
                $guest->rsvpResponse()->updateOrCreate([], ['status' => $status, 'responded_at' => now()]);
            }
            if ($changes !== []) {
                $group->increment('rsvp_version');
            }
            $group->guests->load('rsvpResponse');

            return $this->presentation($group, $token);
        });
    }

    /** Lock order: wedding, group, then its ordered members; all writes serialize on the group. */
    private function withInvitation(string $token, Closure $operation): array
    {
        $hash = hash('sha256', $token);
        $candidate = InvitationGroup::query()->where('invitation_token_hash', $hash)->firstOrFail(['id', 'wedding_id']);

        return DB::transaction(function () use ($candidate, $hash, $operation): array {
            $wedding = Wedding::query()->whereKey($candidate->wedding_id)->lockForUpdate()->firstOrFail();
            $group = $wedding->invitationGroups()->whereKey($candidate->id)->where('invitation_token_hash', $hash)->lockForUpdate()->firstOrFail();
            abort_unless($group->linkStatus() === 'active', 404);
            $this->published->revision($wedding);
            $group->setRelation('guests', $group->guests()->orderBy('id')->lockForUpdate()->get()->load('rsvpResponse'));

            return $operation($wedding, $group);
        });
    }

    private function reference(Guest $guest, string $token): string
    {
        return hash_hmac('sha256', (string) $guest->id, $token);
    }

    private function presentation(InvitationGroup $group, string $token): array
    {
        return ['version' => $group->rsvp_version, 'guests' => $group->guests->map(fn (Guest $guest): array => [
            'reference' => $this->reference($guest, $token), 'name' => $guest->name,
            'status' => $guest->rsvpResponse?->status ?? 'pending',
            'respondedAt' => $guest->rsvpResponse?->responded_at->toIso8601String(),
        ])->all()];
    }
}
