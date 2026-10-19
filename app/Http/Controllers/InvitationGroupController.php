<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManageWeddingGuestsRequest;
use App\Http\Requests\StoreInvitationGroupRequest;
use App\Http\Requests\UpdateInvitationGroupRequest;
use App\Models\Account;
use App\Models\InvitationGroup;
use App\Models\Wedding;
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
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'page' => ['nullable', 'integer', 'min:1']]);
        $search = $filters['search'] ?? '';
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $groups = $wedding->invitationGroups()
            ->when($search !== '', function (Builder $query) use ($pattern): void {
                $query->where(function (Builder $query) use ($pattern): void {
                    $query->whereRaw("label LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereHas('guests', fn (Builder $guests) => $guests->whereRaw("name LIKE ? ESCAPE '!'", [$pattern]));
                });
            })
            ->with(['guests' => fn ($query) => $query->select('id', 'invitation_group_id', 'name')->withExists('rsvpResponse')->orderBy('id')])
            ->withCount('guests')->orderByDesc('id')->paginate(25)->appends(['search' => $search]);
        $groups->through(fn (InvitationGroup $group): array => [
            'id' => $group->id, 'label' => $group->label,
            'contact_email' => $group->contact_email, 'contact_phone' => $group->contact_phone,
            'guests_count' => $group->guests_count,
            'link_status' => $group->linkStatus(), 'link_version' => $group->invitation_link_version,
            'guests' => $group->guests->map(fn ($guest): array => ['id' => $guest->id, 'name' => $guest->name, 'has_response' => (bool) $guest->rsvp_response_exists])->all(),
        ]);

        return Inertia::render('weddings/Guests', [
            'account' => ['id' => $account->id, 'name' => $account->name],
            'wedding' => ['id' => $wedding->id, 'partner_one_name' => $wedding->partner_one_name, 'partner_two_name' => $wedding->partner_two_name],
            'groups' => $groups, 'filters' => ['search' => $search],
            'totals' => ['groups' => $wedding->invitationGroups()->count(), 'people' => $wedding->guests()->count()],
            'maxGuestsPerGroup' => InvitationGroup::MAX_GUESTS,
        ]);
    }

    public function store(StoreInvitationGroupRequest $request, Account $account, Wedding $wedding): RedirectResponse
    {
        $attributes = $request->validated();
        DB::transaction(function () use ($wedding, $attributes): void {
            $group = $wedding->invitationGroups()->create(collect($attributes)->except('guests')->all());
            $group->guests()->createMany($attributes['guests']);
        });

        return to_route('accounts.weddings.guests.index', [$account, $wedding]);
    }

    public function update(UpdateInvitationGroupRequest $request, Account $account, Wedding $wedding, InvitationGroup $invitationGroup): RedirectResponse
    {
        DB::transaction(function () use ($request, $wedding, $invitationGroup): void {
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
        DB::transaction(function () use ($wedding, $invitationGroup): void {
            $wedding->invitationGroups()->whereKey($invitationGroup->id)->lockForUpdate()->firstOrFail()->delete();
        });

        return to_route('accounts.weddings.guests.index', [$account, $wedding]);
    }
}
