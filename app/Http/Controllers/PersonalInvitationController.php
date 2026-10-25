<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitPersonalRsvpRequest;
use App\Services\InvitationLink;
use App\Services\PersonalRsvp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PersonalInvitationController extends Controller
{
    public function show(Request $request, PersonalRsvp $rsvp): Response|JsonResponse
    {
        $token = (string) $request->route('token');

        return $request->expectsJson()
            ? response()->json(['rsvp' => $rsvp->read($token)], 200, InvitationLink::HEADERS)
            : $rsvp->page($token);
    }

    public function store(SubmitPersonalRsvpRequest $request, PersonalRsvp $rsvp): JsonResponse
    {
        return response()->json($rsvp->submitWithReceipt((string) $request->route('token'), $request->safe()->except('_token')), 200, InvitationLink::HEADERS);
    }
}
