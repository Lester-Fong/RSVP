<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitRsvpRequest;
use App\Services\InvitationLink;
use App\Services\InvitationRsvp;
use Illuminate\Http\JsonResponse;

class PublicRsvpController extends Controller
{
    public function csrf(): JsonResponse
    {
        return response()->json(['csrfToken' => csrf_token()], 200, InvitationLink::HEADERS);
    }

    public function store(SubmitRsvpRequest $request, InvitationRsvp $rsvp): JsonResponse
    {
        return response()->json([
            'message' => 'Your responses have been saved.',
            'rsvp' => $rsvp->submit((string) $request->route('token'), $request->validated()),
        ], 200, InvitationLink::HEADERS);
    }
}
