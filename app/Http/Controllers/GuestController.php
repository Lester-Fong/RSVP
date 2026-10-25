<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManageWeddingGuestsRequest;
use App\Http\Requests\StoreGuestRequest;
use App\Http\Requests\UpdateGuestRequest;
use App\Models\Account;
use App\Models\Guest;
use App\Models\InvitationGroup;
use App\Models\Wedding;
use App\Services\PersonalRsvpTransaction;
use App\Services\RsvpMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuestController extends Controller
{
    public function store(StoreGuestRequest $request, Account $account, Wedding $wedding, InvitationGroup $invitationGroup): RedirectResponse
    {
        DB::transaction(function () use ($request, $wedding, $invitationGroup): void {
            Wedding::query()->whereKey($wedding->id)->lockForUpdate()->firstOrFail();
            $group = $wedding->invitationGroups()->whereKey($invitationGroup->id)->lockForUpdate()->firstOrFail();
            if ($group->guests()->count() >= InvitationGroup::MAX_GUESTS) {
                throw ValidationException::withMessages(['name' => 'A group can contain at most '.InvitationGroup::MAX_GUESTS.' guests. Create another group for additional people.']);
            }
            $group->guests()->create($request->validated());
            $group->increment('rsvp_version');
        });

        return to_route('accounts.weddings.guests.index', [$account, $wedding]);
    }

    public function update(UpdateGuestRequest $request, Account $account, Wedding $wedding, InvitationGroup $invitationGroup, Guest $guest): RedirectResponse
    {
        if (app(RsvpMode::class)->personal()) {
            app(PersonalRsvpTransaction::class)->run($wedding->id, $invitationGroup->id, function (Wedding $locked, InvitationGroup $group) use ($request, $guest): void {
                app(PersonalRsvpTransaction::class)->member($group, $guest->id)->update($request->validated());
            }, $request->user());

            return to_route('accounts.weddings.guests.index', [$account, $wedding]);
        }
        DB::transaction(function () use ($request, $wedding, $invitationGroup, $guest): void {
            Wedding::query()->whereKey($wedding->id)->lockForUpdate()->firstOrFail();
            $group = $wedding->invitationGroups()->whereKey($invitationGroup->id)->lockForUpdate()->firstOrFail();
            $group->guests()->whereKey($guest->id)->firstOrFail()->update($request->validated());
            $group->increment('rsvp_version');
        });

        return to_route('accounts.weddings.guests.index', [$account, $wedding]);
    }

    public function destroy(ManageWeddingGuestsRequest $request, Account $account, Wedding $wedding, InvitationGroup $invitationGroup, Guest $guest): RedirectResponse
    {
        if (app(RsvpMode::class)->personal()) {
            app(PersonalRsvpTransaction::class)->run($wedding->id, $invitationGroup->id, function (Wedding $locked, InvitationGroup $group) use ($guest): void {
                if ($group->guests->count() <= 1) {
                    throw ValidationException::withMessages(['guest' => 'Keep at least one guest in this group.']);
                }
                app(PersonalRsvpTransaction::class)->member($group, $guest->id)->delete();
            }, $request->user());

            return to_route('accounts.weddings.guests.index', [$account, $wedding]);
        }
        DB::transaction(function () use ($wedding, $invitationGroup, $guest): void {
            Wedding::query()->whereKey($wedding->id)->lockForUpdate()->firstOrFail();
            $group = $wedding->invitationGroups()->whereKey($invitationGroup->id)->lockForUpdate()->firstOrFail();
            if ($group->guests()->count() <= 1) {
                throw ValidationException::withMessages(['guest' => 'Keep at least one guest in this group. Delete the entire group if it is no longer needed.']);
            }
            $group->guests()->whereKey($guest->id)->firstOrFail()->delete();
            $group->increment('rsvp_version');
        });

        return to_route('accounts.weddings.guests.index', [$account, $wedding]);
    }
}
