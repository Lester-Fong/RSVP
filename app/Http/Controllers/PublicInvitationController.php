<?php

namespace App\Http\Controllers;

use App\Services\InvitationRsvp;
use App\Services\PublishedWedding;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicInvitationController extends Controller
{
    public function show(Request $request, PublishedWedding $published, InvitationRsvp $rsvp): Response
    {
        $invitation = $rsvp->read((string) $request->route('token'));

        return $published->render($invitation['wedding'], $invitation['greeting'], $invitation['rsvp']);
    }
}
