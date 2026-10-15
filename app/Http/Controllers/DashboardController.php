<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $account = $request->user()
            ->accounts()
            ->wherePivot('role', 'owner')
            ->orderBy('accounts.id')
            ->first();

        $weddings = $account?->weddings()
            ->orderByRaw('wedding_date IS NULL')
            ->orderBy('wedding_date')
            ->orderBy('id')
            ->get()
            ->map(fn ($wedding) => [
                'id' => $wedding->id,
                'partner_one_name' => $wedding->partner_one_name,
                'partner_two_name' => $wedding->partner_two_name,
                'wedding_date' => $wedding->wedding_date?->format('Y-m-d'),
                'status' => $wedding->status,
            ]) ?? collect();

        return Inertia::render('Dashboard', [
            'account' => $account ? [
                'id' => $account->id,
                'name' => $account->name,
            ] : null,
            'weddings' => $weddings,
        ]);
    }
}
