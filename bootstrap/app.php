<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Services\InvitationLink;
use App\Services\RedactInvitationTokens;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (Throwable $exception) {
            if (request()->is('invite', 'invite/*', 'invitation-rsvp/*')) {
                Log::error('Personalized invitation request failed', [
                    'type' => $exception::class,
                    'message' => RedactInvitationTokens::text($exception->getMessage()),
                    'trace' => RedactInvitationTokens::text($exception->getTraceAsString()),
                ]);

                return false;
            }
        });
        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($request->is('invite', 'invite/*', 'invitation-rsvp/*')) {
                $status = $exception instanceof ValidationException ? 422
                    : ($exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500);
                $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

                if ($request->isMethod('POST') || $request->expectsJson()) {
                    $retryAfter = $status === 429 ? max(1, (int) ($headers['Retry-After'] ?? 60)) : null;
                    $message = match ($status) {
                        422 => $exception instanceof ValidationException ? ($exception->errors()['rsvp'][0] ?? 'Check your choices and try again. Your saved responses have not changed.') : 'Check your choices and try again.',
                        419 => 'Your session expired. Try saving again to refresh it. Your saved responses have not changed.',
                        429 => 'Too many requests. Try again in '.$retryAfter.' seconds. Your saved responses have not changed.',
                        404 => 'This invitation is unavailable.',
                        default => 'Your responses could not be saved. Please try again. Your saved responses have not changed.',
                    };

                    return response()->json([
                        'message' => $message,
                        ...($retryAfter !== null ? ['retryAfter' => $retryAfter] : []),
                    ], $status, [...$headers, ...InvitationLink::HEADERS]);
                }

                return response($status === 429 ? 'Too many requests. Please try again shortly.' : 'This invitation is unavailable.', $status, [...$headers, ...InvitationLink::HEADERS]);
            }
            if ($request->routeIs('accounts.weddings.invitation-links.*')) {
                $status = $exception instanceof ValidationException ? 422
                    : ($exception instanceof AuthenticationException ? 401
                    : ($exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500));

                return response()->json([
                    'message' => 'The invitation link request could not be completed.',
                    'errors' => $exception instanceof ValidationException ? $exception->errors() : [],
                ], $status, InvitationLink::HEADERS);
            }
        });
    })->create();
