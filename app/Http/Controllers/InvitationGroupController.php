<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManageWeddingGuestsRequest;
use App\Http\Requests\StoreInvitationGroupRequest;
use App\Http\Requests\UpdateInvitationGroupRequest;
use App\Http\Requests\ViewAttendanceRequest;
use App\Models\Account;
use App\Models\InvitationGroup;
use App\Models\Wedding;
use App\Services\InvitationEligibility;
use App\Services\PersonalRsvpTransaction;
use App\Services\RsvpMode;
use App\Services\WeddingAttendance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class InvitationGroupController extends Controller
{
    public function index(ViewAttendanceRequest $request, Account $account, Wedding $wedding, WeddingAttendance $attendance): Response
    {
        $filters = $request->filters();
        $eligibility = app(InvitationEligibility::class);
        $invitationState = $eligibility->enabled ? $filters['invitation_state'] : 'active';
        $filters['invitation_state'] = $invitationState;
        if ($eligibility->enabled) {
            $eligibility->assertSchema();
        }
        $guestColumns = ['guests.id', 'invitation_group_id', 'name'];
        if ($eligibility->enabled) {
            $guestColumns[] = 'withdrawn_at';
        }
        $groups = null;
        $individuals = null;
        if ($filters['view'] === 'guests') {
            $individuals = $attendance->guests($wedding, $filters);
        } else {
            $matchingGroupIds = $attendance->matchingGuests($wedding, $filters)->select('guests.invitation_group_id');
            $groups = $wedding->invitationGroups()
                ->where(function (Builder $query) use ($matchingGroupIds, $filters, $invitationState): void {
                    $query->whereIn('invitation_groups.id', $matchingGroupIds);
                    if ($filters['status'] === 'all' && $invitationState !== 'withdrawn') {
                        $query->orWhere(function (Builder $empty) use ($filters): void {
                            $empty->whereDoesntHave('guests');
                            if ($filters['search'] !== '') {
                                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search']).'%';
                                $empty->whereRaw("label LIKE ? ESCAPE '!'", [$pattern]);
                            }
                        });
                    }
                })
                ->with(['guests' => fn ($query) => $eligibility->constrain($query->select($guestColumns), $invitationState)->with('rsvpResponse:id,guest_id,status,responded_at')->orderBy('id')])
                ->withCount(['guests' => fn (Builder $query) => $eligibility->constrain($query, $invitationState)])
                ->orderByDesc('invitation_groups.id')->paginate(25)->appends($filters);
            $groups->through(fn (InvitationGroup $group): array => [
                'id' => $group->id, 'label' => $group->label,
                'guests_count' => $group->guests_count,
                'attending' => $group->guests->filter(fn ($guest): bool => $guest->rsvpResponse?->status === 'attending')->count(),
                'declined' => $group->guests->filter(fn ($guest): bool => $guest->rsvpResponse?->status === 'declined')->count(),
                'pending' => $group->guests->filter(fn ($guest): bool => $guest->rsvpResponse === null)->count(),
                'link_status' => $group->linkStatus(), 'link_version' => $group->invitation_link_version,
                'guests' => $group->guests->map(fn ($guest): array => ['id' => $guest->id, 'name' => $guest->name, 'has_response' => $guest->rsvpResponse !== null,
                    'invitation_state' => $eligibility->enabled && $guest->withdrawn_at !== null ? 'withdrawn' : 'active',
                    'rsvp_status' => $guest->rsvpResponse?->status ?? 'pending',
                    'responded_at' => $guest->rsvpResponse?->responded_at?->toIso8601String()])->all(),
            ]);
        }

        $response = Inertia::render('weddings/Guests', [
            'account' => ['id' => $account->id, 'name' => $account->name],
            'wedding' => ['id' => $wedding->id, 'partner_one_name' => $wedding->partner_one_name, 'partner_two_name' => $wedding->partner_two_name],
            'groups' => $groups, 'individuals' => $individuals, 'filters' => $filters,
            'summary' => $attendance->summary($wedding),
            'matchedGuests' => $individuals?->total() ?? $attendance->matchingGuests($wedding, $filters)->count(),
            'refreshedAt' => now()->toIso8601String(),
            'totals' => ['groups' => $wedding->invitationGroups()->count(), 'people' => $wedding->guests()->count(),
                'active_people' => $eligibility->enabled ? $wedding->guests()->whereNull('withdrawn_at')->count() : $wedding->guests()->count(),
                'withdrawn_people' => $eligibility->enabled ? $wedding->guests()->whereNotNull('withdrawn_at')->count() : 0],
            'withdrawalEnabled' => $eligibility->enabled,
            'maxGuestsPerGroup' => InvitationGroup::MAX_GUESTS,
        ])->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function store(StoreInvitationGroupRequest $request, Account $account, Wedding $wedding): RedirectResponse
    {
        $attributes = $request->validated();
        DB::transaction(function () use ($wedding, $attributes): void {
            Wedding::query()->whereKey($wedding->id)->lockForUpdate()->firstOrFail();
            $group = $wedding->invitationGroups()->create(collect($attributes)->except('guests')->all());
            $group->guests()->createMany($attributes['guests']);
        });

        return to_route('accounts.weddings.guests.index', [$account, $wedding]);
    }

    public function update(UpdateInvitationGroupRequest $request, Account $account, Wedding $wedding, InvitationGroup $invitationGroup): RedirectResponse
    {
        DB::transaction(function () use ($request, $wedding, $invitationGroup): void {
            Wedding::query()->whereKey($wedding->id)->lockForUpdate()->firstOrFail();
            $group = $wedding->invitationGroups()->whereKey($invitationGroup->id)->lockForUpdate()->firstOrFail();
            $group->fill($request->validated());
            if ($group->isDirty('label')) {
                $group->rsvp_version++;
            }
            $group->save();
        });

        return to_route('accounts.weddings.guests.index', [$account, $wedding]);
    }

    public function destroy(ManageWeddingGuestsRequest $request, Account $account, Wedding $wedding, InvitationGroup $invitationGroup): RedirectResponse
    {
        if (app(RsvpMode::class)->personal()) {
            app(PersonalRsvpTransaction::class)->run($wedding->id, $invitationGroup->id, function (Wedding $locked, InvitationGroup $group): void {
                $group->delete();
            }, $request->user());

            return to_route('accounts.weddings.guests.index', [$account, $wedding]);
        }
        DB::transaction(function () use ($wedding, $invitationGroup): void {
            Wedding::query()->whereKey($wedding->id)->lockForUpdate()->firstOrFail();
            $wedding->invitationGroups()->whereKey($invitationGroup->id)->lockForUpdate()->firstOrFail()->delete();
        });

        return to_route('accounts.weddings.guests.index', [$account, $wedding]);
    }
}
