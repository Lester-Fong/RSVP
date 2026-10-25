<?php

namespace App\Http\Controllers;

use App\Http\Requests\InspectInvitationLifecycleRequest;
use App\Http\Requests\InvitationLifecycleRequest;
use App\Models\Account;
use App\Models\Guest;
use App\Models\InvitationGroup;
use App\Models\Wedding;
use App\Services\GuestInvitationLifecycle;
use App\Services\InvitationEligibility;
use App\Services\InvitationLink;
use Illuminate\Http\JsonResponse;

class GuestInvitationLifecycleController extends Controller
{
    public function inspect(InspectInvitationLifecycleRequest $request, Account $account, Wedding $wedding, InvitationGroup $invitationGroup, Guest $guest, string $action, GuestInvitationLifecycle $lifecycle): JsonResponse
    {
        abort_unless(app(InvitationEligibility::class)->enabled, 404);

        return response()->json(['confirmation' => $lifecycle->inspect($request->user(), $wedding, $invitationGroup, $guest, $action)], 200, InvitationLink::HEADERS);
    }

    public function change(InvitationLifecycleRequest $request, Account $account, Wedding $wedding, InvitationGroup $invitationGroup, Guest $guest, string $action, GuestInvitationLifecycle $lifecycle): JsonResponse
    {
        abort_unless(app(InvitationEligibility::class)->enabled, 404);
        $input = $request->validated();
        $result = $action === 'withdraw'
            ? $lifecycle->withdraw($request->user(), $wedding, $invitationGroup, $guest, $input)
            : $lifecycle->reinstate($request->user(), $wedding, $invitationGroup, $guest, $input);

        return response()->json(['result' => $result], 200, InvitationLink::HEADERS);
    }
}
