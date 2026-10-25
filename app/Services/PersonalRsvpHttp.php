<?php

namespace App\Services;

use App\Exceptions\PersonalRsvpValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class PersonalRsvpHttp
{
    public static function handles(Request $request): bool
    {
        return $request->is('rsvp', 'rsvp/*', 'accounts/*/weddings/*/invitation-groups/*/rsvp-access', 'accounts/*/weddings/*/invitation-groups/*/rsvp-access/*');
    }

    public static function render(Throwable $error, Request $request): Response
    {
        $status = $error instanceof ValidationException ? 422 : ($error instanceof AuthenticationException ? 401 : ($error instanceof HttpExceptionInterface ? $error->getStatusCode() : 500));
        $headers = $error instanceof HttpExceptionInterface ? $error->getHeaders() : [];
        $kind = $error instanceof PersonalRsvpValidationException ? $error->kind : match ($status) {
            401, 419 => 'session', 404, 403, 503 => 'unavailable', 422 => 'validation', 429 => 'rate_limited', default => 'network',
        };
        $retryAfter = $status === 429 ? max(1, (int) ($headers['Retry-After'] ?? 60)) : null;
        $message = $status === 429 ? 'Too many requests. Try again in '.$retryAfter.' seconds.' : ($status === 422 ? 'This change could not be saved. Reload and review the current information.' : 'This invitation or operation is unavailable.');
        if ($request->expectsJson() || ! $request->isMethod('GET') || ! $request->is('rsvp', 'rsvp/*')) {
            return response()->json(['kind' => $kind, 'message' => $message, ...($retryAfter === null ? [] : ['retryAfter' => $retryAfter])], $status, [...$headers, ...InvitationLink::HEADERS]);
        }

        return response($message, $status, [...$headers, ...InvitationLink::HEADERS]);
    }
}
