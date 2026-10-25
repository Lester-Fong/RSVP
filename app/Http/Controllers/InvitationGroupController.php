<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManageWeddingGuestsRequest;
use App\Http\Requests\StoreInvitationGroupRequest;
use App\Http\Requests\UpdateInvitationGroupRequest;
use App\Models\Account;
use App\Models\InvitationGroup;
use App\Models\Wedding;
use App\Services\InvitationEligibility;
use App\Services\PersonalRsvpTransaction;
use App\Services\RsvpMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvitationGroupController extends Controller
{
    public function index(Request $request, Account $account, Wedding $wedding): Response
    {
        $this->authorize('view', $wedding);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'page' => ['nullable', 'integer', 'min:1'], 'invitation_state' => ['nullable', 'in:active,withdrawn,all']]);
        $search = $filters['search'] ?? '';
        $eligibility = app(InvitationEligibility::class);
        $invitationState = $eligibility->enabled ? ($filters['invitation_state'] ?? 'active') : 'all';
        if ($eligibility->enabled) {
            $eligibility->assertSchema();
        }
        $guestColumns = ['guests.id', 'invitation_group_id', 'name'];
        if ($eligibility->enabled) {
            $guestColumns[] = 'withdrawn_at';
        }
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $groups = $wedding->invitationGroups()
            ->when($invitationState !== 'all', fn (Builder $query) => $query->whereHas('guests', fn (Builder $guests) => $eligibility->constrain($guests, $invitationState)))
            ->when($search !== '', function (Builder $query) use ($pattern): void {
                $query->where(function (Builder $query) use ($pattern): void {
                    $query->whereRaw("label LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereHas('guests', fn (Builder $guests) => $eligibility->constrain($guests->whereRaw("name LIKE ? ESCAPE '!'", [$pattern]), $invitationState));
                });
            })
            ->with(['guests' => fn ($query) => $eligibility->constrain($query->select($guestColumns), $invitationState)->with('rsvpResponse:id,guest_id,status,responded_at')->orderBy('id')])
            ->withCount(['guests' => fn (Builder $query) => $eligibility->constrain($query, $invitationState)])->orderByDesc('id')->paginate(25)->appends(['search' => $search, 'invitation_state' => $invitationState]);
        $groups->through(fn (InvitationGroup $group): array => [
            'id' => $group->id, 'label' => $group->label,
            'contact_email' => $group->contact_email, 'contact_phone' => $group->contact_phone,
            'guests_count' => $group->guests_count,
            'link_status' => $group->linkStatus(), 'link_version' => $group->invitation_link_version,
            'guests' => $group->guests->map(fn ($guest): array => ['id' => $guest->id, 'name' => $guest->name, 'has_response' => $guest->rsvpResponse !== null,
                'invitation_state' => $eligibility->enabled && $guest->withdrawn_at !== null ? 'withdrawn' : 'active', 'rsvp_status' => $guest->rsvpResponse?->status ?? 'pending'])->all(),
        ]);

        return Inertia::render('weddings/Guests', [
            'account' => ['id' => $account->id, 'name' => $account->name],
            'wedding' => ['id' => $wedding->id, 'partner_one_name' => $wedding->partner_one_name, 'partner_two_name' => $wedding->partner_two_name],
            'groups' => $groups, 'filters' => ['search' => $search, 'invitation_state' => $invitationState],
            'totals' => ['groups' => $wedding->invitationGroups()->count(), 'people' => $wedding->guests()->count(),
                'active_people' => $eligibility->enabled ? $wedding->guests()->whereNull('withdrawn_at')->count() : $wedding->guests()->count(),
                'withdrawn_people' => $eligibility->enabled ? $wedding->guests()->whereNotNull('withdrawn_at')->count() : 0],
            'withdrawalEnabled' => $eligibility->enabled,
            'maxGuestsPerGroup' => InvitationGroup::MAX_GUESTS,
        ]);
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
