<?php

namespace App\Services;

use App\Exceptions\PersonalRsvpValidationException;
use App\Models\Guest;
use App\Models\GuestInvitationLink;
use App\Models\InvitationGroup;
use App\Models\Wedding;
use Closure;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use SensitiveParameter;

class PersonalRsvp
{
    public function __construct(private PersonalRsvpTransaction $transactions, private PublishedWedding $published) {}

    /** RSVP/greeting projection only; no live wedding model or private IDs leave this boundary. */
    public function read(#[SensitiveParameter] string $token): array
    {
        return $this->withCredential($token, fn (Guest $holder, Collection $targets): array => $this->presentation($holder, $targets, $token));
    }

    public function page(#[SensitiveParameter] string $token): Response
    {
        return $this->withCredential($token, function (Guest $holder, Collection $targets, Wedding $wedding) use ($token): Response {
            $state = $this->presentation($holder, $targets, $token);

            return $this->published->render($wedding, ['label' => $state['greeting']['name'], 'guests' => array_column($state['guests'], 'name')], $state, personal: true);
        });
    }

    public function submit(#[SensitiveParameter] string $token, #[SensitiveParameter] array $input): array
    {
        return $this->submitWithReceipt($token, $input)['rsvp'];
    }

    /** @return array{rsvp: array, receipt: array{changed: bool}} */
    public function submitWithReceipt(#[SensitiveParameter] string $token, #[SensitiveParameter] array $input): array
    {
        return $this->withCredential($token, function (Guest $holder, Collection $targets) use ($token, $input): array {
            $validator = Validator::make($input, [
                'access_version' => ['required', 'integer', 'min:0', 'max:'.PersonalRsvpTransaction::MAX_VERSION],
                'responses' => ['required', 'array', 'list', 'min:1', 'max:'.InvitationGroup::MAX_GUESTS],
                'responses.*' => ['required', 'array:guest,version,status'],
                'responses.*.guest' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/D', 'distinct:strict'],
                'responses.*.version' => ['required', 'integer', 'min:0', 'max:'.PersonalRsvpTransaction::MAX_VERSION],
                'responses.*.status' => ['required', 'string', 'in:attending,declined'],
            ]);
            if (array_diff(array_keys($input), ['access_version', 'responses']) !== [] || $validator->fails()) {
                $this->fail('Choose Attending or Declining for only the people and versions shown in this invitation. Your saved responses have not changed.');
            }
            if ((int) $input['access_version'] !== $holder->rsvp_access_version) {
                $this->fail('Your RSVP permissions changed. Reload and review who this link can answer for. Your saved responses have not changed.', 'stale_access');
            }
            $allowed = $targets->keyBy(fn (Guest $guest): string => $this->reference($guest, $token));
            $changes = [];
            foreach ($input['responses'] as $entry) {
                $guest = $allowed->get($entry['guest']);
                if ($guest === null) {
                    $this->fail('A selected person is unavailable for this link. Reload before trying again. Your saved responses have not changed.');
                }
                $changed = $guest->rsvpResponse?->status !== $entry['status'];
                $version = (int) $entry['version'];
                if ($version > $guest->rsvp_version || ($changed && $version !== $guest->rsvp_version)) {
                    $this->fail('A selected response or name changed in another window. Reload and review the saved choices. Your saved responses have not changed.', 'stale_response');
                }
                if ($changed) {
                    $changes[] = [$guest, $entry['status']];
                }
            }
            usort($changes, fn (array $first, array $second): int => $first[0]->id <=> $second[0]->id);
            $respondedAt = now();
            foreach ($changes as [$guest, $status]) {
                $guest->rsvp_version = $this->transactions->nextVersion($guest->rsvp_version);
                $response = $guest->rsvpResponse()->updateOrCreate([], ['status' => $status, 'responded_at' => $respondedAt]);
                $guest->save();
                $guest->setRelation('rsvpResponse', $response);
            }

            return ['rsvp' => $this->presentation($holder, $targets, $token), 'receipt' => ['changed' => $changes !== []]];
        });
    }

    private function withCredential(#[SensitiveParameter] string $token, Closure $operation): mixed
    {
        $this->transactions->assertEligibilitySchema();
        abort_unless(preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1, 404);
        $hash = hash('sha256', $token);
        $candidate = GuestInvitationLink::query()->where('token_hash', $hash)->firstOrFail(['guest_id']);
        $guest = Guest::query()->whereKey($candidate->guest_id)->firstOrFail(['id', 'invitation_group_id']);
        $group = InvitationGroup::query()->whereKey($guest->invitation_group_id)->firstOrFail(['id', 'wedding_id']);

        return $this->transactions->run($group->wedding_id, $group->id, function (Wedding $wedding, InvitationGroup $group) use ($guest, $hash, $operation): mixed {
            $holder = $this->transactions->member($group, $guest->id);
            abort_unless($this->transactions->eligible($holder) && $this->transactions->isIndependent($holder) && $this->transactions->hasActiveCredential($holder)
                && hash_equals($holder->invitationLink->token_hash, $hash), 404);
            $this->published->revision($wedding, personal: true);
            $targets = collect([$holder]);
            foreach ($holder->representedDelegations as $slot) {
                $dependent = $this->transactions->member($group, $slot->dependent_guest_id);
                abort_unless($this->transactions->eligible($dependent) && $dependent->id !== $holder->id && $dependent->rsvpDelegation?->representative_guest_id === $holder->id
                    && $dependent->representedDelegations->isEmpty() && ! $this->transactions->hasActiveCredential($dependent), 404);
                $targets->push($dependent);
            }

            return $operation($holder, $targets, $wedding);
        });
    }

    private function presentation(Guest $holder, Collection $targets, #[SensitiveParameter] string $token): array
    {
        return ['greeting' => ['name' => $holder->name], 'access_version' => $holder->rsvp_access_version,
            'guests' => $targets->map(fn (Guest $guest): array => [
                'reference' => $this->reference($guest, $token), 'name' => $guest->name, 'version' => $guest->rsvp_version,
                'represented' => $guest->id !== $holder->id, 'status' => $guest->rsvpResponse?->status ?? 'pending',
                'respondedAt' => $guest->rsvpResponse?->responded_at->toIso8601String(),
            ])->all()];
    }

    private function reference(Guest $guest, #[SensitiveParameter] string $token): string
    {
        return hash_hmac('sha256', 'personal-rsvp:'.$guest->id, $token);
    }

    private function fail(string $message, string $kind = 'validation'): never
    {
        throw PersonalRsvpValidationException::because('rsvp', $message, $kind);
    }
}
