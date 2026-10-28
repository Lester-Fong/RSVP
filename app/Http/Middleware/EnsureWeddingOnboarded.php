<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Services\CustomerWorkspace;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureWeddingOnboarded
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($account = $request->route('account')) {
            if ($account instanceof Account) {
                Gate::authorize('view', $account);
            }
        }
        if (! $request->routeIs('accounts.weddings.create', 'accounts.weddings.store')
            && app(CustomerWorkspace::class)->state($request->user())['needsOnboarding']) {
            return to_route('onboarding');
        }

        return $next($request);
    }
}
