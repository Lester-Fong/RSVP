<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWeddingRequest;
use App\Http\Requests\UpdateWeddingRequest;
use App\Models\Account;
use App\Models\Wedding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WeddingController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('weddings/Create', [
            'account' => null,
        ]);
    }

    public function createInAccount(Account $account): Response
    {
        $this->authorize('createWedding', $account);

        return Inertia::render('weddings/Create', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
            ],
        ]);
    }

    public function storeFirst(StoreWeddingRequest $request): RedirectResponse
    {
        $user = $request->user();
        $attributes = $request->validated();

        $wedding = DB::transaction(function () use ($user, $attributes) {
            // Serialize onboarding for this user so concurrent first-wedding
            // requests cannot both create an owner account.
            $user = $user->newQuery()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $account = $user->accounts()
                ->wherePivot('role', 'owner')
                ->orderBy('accounts.id')
                ->first();

            if (! $account) {
                $account = Account::create([
                    'name' => $user->name."'s Wedding Workspace",
                ]);

                $account->users()->attach($user->getKey(), [
                    'role' => 'owner',
                ]);
            }

            return $account->weddings()->create([
                ...$attributes,
                'public_slug' => $this->generateUniqueSlug(
                    $attributes['partner_one_name'],
                    $attributes['partner_two_name'],
                ),
            ]);
        });

        return to_route('accounts.weddings.show', [
            'account' => $wedding->account_id,
            'wedding' => $wedding->id,
        ]);
    }

    public function store(StoreWeddingRequest $request, Account $account): RedirectResponse
    {
        $attributes = $request->validated();
        $wedding = DB::transaction(function () use ($account, $attributes) {
            // Coordinate with profile deletion, which locks owned accounts
            // before deciding whether their wedding data would be orphaned.
            $account = Account::query()
                ->whereKey($account->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorize('createWedding', $account);

            return $account->weddings()->create([
                ...$attributes,
                'public_slug' => $this->generateUniqueSlug(
                    $attributes['partner_one_name'],
                    $attributes['partner_two_name'],
                ),
            ]);
        });

        return to_route('accounts.weddings.show', [
            'account' => $account->id,
            'wedding' => $wedding->id,
        ]);
    }

    public function show(Account $account, Wedding $wedding): Response
    {
        $this->authorize('view', $wedding);

        return Inertia::render('weddings/Show', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
            ],
            'wedding' => $this->weddingData($wedding),
        ]);
    }

    public function edit(Account $account, Wedding $wedding): Response
    {
        $this->authorize('update', $wedding);

        return Inertia::render('weddings/Edit', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
            ],
            'wedding' => $this->weddingData($wedding),
        ]);
    }

    public function update(UpdateWeddingRequest $request, Account $account, Wedding $wedding): RedirectResponse
    {
        $this->authorize('update', $wedding);
        $wedding->update($request->validated());

        return to_route('accounts.weddings.show', [
            'account' => $account->id,
            'wedding' => $wedding->id,
        ]);
    }

    /** @return array<string, int|string|null> */
    private function weddingData(Wedding $wedding): array
    {
        return [
            'id' => $wedding->id,
            'partner_one_name' => $wedding->partner_one_name,
            'partner_two_name' => $wedding->partner_two_name,
            'wedding_date' => $wedding->wedding_date?->format('Y-m-d'),
            'wedding_time' => $wedding->wedding_time ? substr($wedding->wedding_time, 0, 5) : null,
            'ceremony_venue' => $wedding->ceremony_venue,
            'reception_venue' => $wedding->reception_venue,
            'public_slug' => $wedding->public_slug,
            'status' => $wedding->status,
        ];
    }

    private function generateUniqueSlug(string $partnerOne, string $partnerTwo): string
    {
        $base = Str::slug($partnerOne.'-'.$partnerTwo);
        $base = substr($base !== '' ? $base : 'wedding', 0, 240);

        do {
            $slug = $base.'-'.Str::lower(Str::random(8));
        } while (Wedding::where('public_slug', $slug)->exists());

        return $slug;
    }
}
