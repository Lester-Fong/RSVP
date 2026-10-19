<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvitationLinkRequest;
use App\Models\Account;
use App\Models\InvitationGroup;
use App\Models\Wedding;
use App\Services\InvitationLink;
use Illuminate\Http\JsonResponse;

class InvitationLinkController extends Controller
{
    public function update(InvitationLinkRequest $request, Account $account, Wedding $wedding, InvitationGroup $invitationGroup, string $action, InvitationLink $links): JsonResponse
    {
        return response()->json(
            $links->execute($wedding, $invitationGroup, $action, $request->integer('link_version')),
            200, InvitationLink::HEADERS,
        );
    }
}
