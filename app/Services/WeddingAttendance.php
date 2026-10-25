<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\InvitationGroup;
use App\Models\Wedding;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class WeddingAttendance
{
    public function __construct(private InvitationEligibility $eligibility) {}

    /** @return array{invited: int, attending: int, declined: int, pending: int} */
    public function summary(Wedding $wedding): array
    {
        if ($this->eligibility->enabled) {
            $this->eligibility->assertSchema();
        }
        $counts = Guest::query()->join('invitation_groups', 'guests.invitation_group_id', '=', 'invitation_groups.id')
            ->leftJoin('rsvp_responses', 'guests.id', '=', 'rsvp_responses.guest_id')
            ->where('invitation_groups.wedding_id', $wedding->id)
            ->when($this->eligibility->enabled, fn (Builder $query) => $query->whereNull('guests.withdrawn_at'))
            ->selectRaw('COUNT(guests.id) AS invited,
                COALESCE(SUM(CASE WHEN rsvp_responses.status = ? THEN 1 ELSE 0 END), 0) AS attending,
                COALESCE(SUM(CASE WHEN rsvp_responses.status = ? THEN 1 ELSE 0 END), 0) AS declined,
                COALESCE(SUM(CASE WHEN rsvp_responses.id IS NULL THEN 1 ELSE 0 END), 0) AS pending', ['attending', 'declined'])
            ->toBase()->first();

        return ['invited' => (int) $counts->invited, 'attending' => (int) $counts->attending,
            'declined' => (int) $counts->declined, 'pending' => (int) $counts->pending];
    }

    /** @param array{search: string, status: string, view: string} $filters */
    public function guests(Wedding $wedding, array $filters, ?InvitationGroup $group = null): LengthAwarePaginator
    {
        $query = $this->matchingGuests($wedding, $filters);
        if ($group) {
            $query->where('invitation_group_id', $group->id);
        }

        return $query->select('guests.id', 'guests.name', 'guests.invitation_group_id')
            ->with(['invitationGroup:id,label', 'rsvpResponse:id,guest_id,status,responded_at'])
            ->orderBy('guests.id')->paginate(25)->appends($filters)
            ->through(fn (Guest $guest): array => ['id' => $guest->id, 'name' => $guest->name,
                'group' => $guest->invitationGroup->label, 'status' => $guest->rsvpResponse?->status ?? 'pending',
                'respondedAt' => $guest->rsvpResponse?->responded_at->toIso8601String()]);
    }

    /** @param array{search: string, status: string, view: string} $filters */
    public function groups(Wedding $wedding, array $filters): LengthAwarePaginator
    {
        return $wedding->invitationGroups()->select('invitation_groups.id', 'label')
            ->whereHas('guests', fn (Builder $query) => $this->filter($query, $filters))
            ->withCount(['guests as invited' => fn (Builder $query) => $this->activeOnly($query),
                'guests as attending' => fn (Builder $query) => $this->activeOnly($query)->whereHas('rsvpResponse', fn (Builder $response) => $response->where('status', 'attending')),
                'guests as declined' => fn (Builder $query) => $this->activeOnly($query)->whereHas('rsvpResponse', fn (Builder $response) => $response->where('status', 'declined')),
                'guests as pending' => fn (Builder $query) => $this->activeOnly($query)->whereDoesntHave('rsvpResponse'),
                'guests as matching' => fn (Builder $query) => $this->filter($query, $filters)])
            ->orderBy('invitation_groups.id')->paginate(10)->appends($filters)
            ->through(fn (InvitationGroup $group): array => ['id' => $group->id, 'label' => $group->label,
                'invited' => (int) $group->invited, 'attending' => (int) $group->attending, 'declined' => (int) $group->declined,
                'pending' => (int) $group->pending, 'matching' => (int) $group->matching]);
    }

    /** @param array{search: string, status: string, view: string} $filters */
    public function matchingGuests(Wedding $wedding, array $filters): Builder
    {
        $query = Guest::query()->whereHas('invitationGroup', fn (Builder $group) => $group->where('wedding_id', $wedding->id));
        if ($this->eligibility->enabled) {
            $this->eligibility->assertSchema();
            $query->whereNull('guests.withdrawn_at');
        }

        return $this->filter($query, $filters);
    }

    /** @param array{search: string, status: string, view: string} $filters */
    private function filter(Builder $query, array $filters): Builder
    {
        if ($this->eligibility->enabled) {
            $this->eligibility->assertSchema();
            $query->whereNull('guests.withdrawn_at');
        }
        if ($filters['search'] !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search']).'%';
            $query->where(fn (Builder $query) => $query->whereRaw("guests.name LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereHas('invitationGroup', fn (Builder $group) => $group->whereRaw("label LIKE ? ESCAPE '!'", [$pattern])));
        }
        if ($filters['status'] === 'pending') {
            $query->whereDoesntHave('rsvpResponse');
        } elseif ($filters['status'] !== 'all') {
            $query->whereHas('rsvpResponse', fn (Builder $response) => $response->where('status', $filters['status']));
        }

        return $query;
    }

    private function activeOnly(Builder $query): Builder
    {
        if ($this->eligibility->enabled) {
            $query->whereNull('guests.withdrawn_at');
        }

        return $query;
    }
}
