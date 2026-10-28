<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Wedding;
use App\Services\CustomerWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CustomerWorkspace $workspace): Response|RedirectResponse
    {
        $state = $workspace->state($request->user());
        if ($state['needsOnboarding']) {
            return to_route('onboarding');
        }
        if ($wedding = $state['wedding']) {
            return to_route('accounts.weddings.show', [$wedding->account_id, $wedding->id]);
        }

        return Inertia::render('Dashboard', [
            'workspaces' => $state['accounts']->map(fn (Account $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'weddings' => $account->weddings->map(fn (Wedding $wedding): array => [
                    'id' => $wedding->id,
                    'partner_one_name' => $wedding->partner_one_name,
                    'partner_two_name' => $wedding->partner_two_name,
                ]),
            ]),
        ]);
    }
}
