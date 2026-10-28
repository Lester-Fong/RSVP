<?php

namespace App\Http\Middleware;

use App\Services\RsvpMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceRsvpMode
{
    public function handle(Request $request, Closure $next): Response
    {
        $personal = $request->is('rsvp', 'rsvp/*') || $request->routeIs('accounts.weddings.rsvp-access.*');
        $legacy = $request->is('invite', 'invite/*') || $request->routeIs('accounts.weddings.invitation-links.*');
        $management = $request->routeIs('accounts.weddings.guests.*', 'accounts.weddings.invitation-groups.*');
        $setup = $request->is('invitation-rsvp/*');
        abort_if($legacy, 404);
        if ($personal || $management || $setup) {
            $mode = app(RsvpMode::class);
            abort_unless($mode->personal(), 503);
            $mode->assertSchema();
        }

        return $next($request);
    }
}
