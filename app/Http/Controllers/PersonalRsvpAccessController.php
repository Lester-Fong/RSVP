<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManageWeddingGuestsRequest;
use App\Http\Requests\PersonalCredentialRequest;
use App\Http\Requests\PersonalDelegationRequest;
use App\Models\Account;
use App\Models\Guest;
use App\Models\InvitationGroup;
use App\Models\Wedding;
use App\Services\InvitationLink;
use App\Services\PersonalRsvpAccess;
use Illuminate\Http\JsonResponse;

class PersonalRsvpAccessController extends Controller
{
    public function show(ManageWeddingGuestsRequest $request, Account $account, Wedding $wedding, InvitationGroup $invitationGroup, PersonalRsvpAccess $access): JsonResponse
    {
        return response()->json(['group' => $access->read($request->user(), $wedding, $invitationGroup)], 200, InvitationLink::HEADERS);
    }

    public function credential(PersonalCredentialRequest $request, Account $account, Wedding $wedding, InvitationGroup $invitationGroup, Guest $guest, string $action, PersonalRsvpAccess $access): JsonResponse
    {
        return response()->json($access->credential($request->user(), $wedding, $invitationGroup, $guest, $action, $request->validated()), 200, InvitationLink::HEADERS);
    }

    public function delegation(PersonalDelegationRequest $request, Account $account, Wedding $wedding, InvitationGroup $invitationGroup, Guest $guest, PersonalRsvpAccess $access): JsonResponse
    {
        return response()->json($access->delegate($request->user(), $wedding, $invitationGroup, $guest, $request->validated()), 200, InvitationLink::HEADERS);
    }
}
