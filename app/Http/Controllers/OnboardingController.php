<?php

namespace App\Http\Controllers;

use App\Models\Wedding;
use App\Services\CustomerWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class OnboardingController extends Controller
{
    public function show(Request $request, CustomerWorkspace $workspace): Response|RedirectResponse
    {
        if (! $workspace->state($request->user())['needsOnboarding']) {
            return to_route('dashboard');
        }

        $response = Inertia::render('weddings/Onboarding')->toResponse($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    public function congratulations(Request $request): Response|RedirectResponse
    {
        $receipt = $request->session()->pull('onboarding_created');
        if (! is_array($receipt)) {
            return to_route('dashboard');
        }
        $wedding = Wedding::query()->find($receipt['wedding']);
        if (! $wedding || ! $request->user()->can('view', $wedding)) {
            return to_route('dashboard');
        }

        $response = Inertia::render('weddings/Congratulations', [
            'receipt' => $receipt['id'],
            'partnerOne' => $wedding->partner_one_name,
            'partnerTwo' => $wedding->partner_two_name,
        ])->toResponse($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
