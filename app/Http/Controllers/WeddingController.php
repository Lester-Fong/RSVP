<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWeddingRequest;
use App\Http\Requests\UpdateWeddingRequest;
use App\Models\Account;
use App\Models\WebsiteDraft;
use App\Models\Wedding;
use App\Services\CmsEditorPreview;
use App\Services\CustomerWorkspace;
use App\Services\WeddingPublication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WeddingController extends Controller
{
    public function create(): RedirectResponse
    {
        return to_route('onboarding');
    }

    public function createInAccount(Account $account): RedirectResponse
    {
        $this->authorize('createWedding', $account);

        return to_route('onboarding');
    }

    public function storeFirst(StoreWeddingRequest $request, CustomerWorkspace $workspace): RedirectResponse
    {
        return $this->finishOnboarding($request, $workspace->create($request->user(), $request->validated()));
    }

    public function store(StoreWeddingRequest $request, Account $account, CustomerWorkspace $workspace): RedirectResponse
    {
        return $this->finishOnboarding($request, $workspace->create($request->user(), $request->validated(), $account));
    }

    /** @param array{wedding: Wedding, created: bool} $result */
    private function finishOnboarding(Request $request, array $result): RedirectResponse
    {
        $request->session()->forget('onboarding_created');
        if (! $result['created']) {
            return to_route('dashboard');
        }
        $request->session()->flash('onboarding_created', ['wedding' => $result['wedding']->id, 'id' => (string) Str::uuid()]);

        return to_route('onboarding.congratulations');
    }

    public function show(Account $account, Wedding $wedding, WeddingPublication $publication): Response
    {
        $this->authorize('view', $wedding);
        $cmsEditor = CmsEditorPreview::shouldOpen($wedding);

        return Inertia::render('weddings/Show', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
            ],
            'wedding' => $this->weddingData($wedding),
            'publication' => $publication->summary($wedding),
            'cmsEditorUrl' => $cmsEditor
                ? route('accounts.weddings.cms-preview.show', [$account, $wedding], false) : null,
            'cmsSavedPreviewUrl' => $cmsEditor && ($wedding->websiteDraft?->lock_version ?? 0) > 0
                ? route('accounts.weddings.cms-preview.saved-preview', [$account, $wedding], false) : null,
        ]);
    }

    public function preview(Account $account, Wedding $wedding): Response|RedirectResponse
    {
        $this->authorize('view', $wedding);
        $draft = $wedding->websiteDraft;
        if (CmsEditorPreview::shouldOpen($wedding)) {
            return to_route('accounts.weddings.cms-preview.saved-preview', [$account, $wedding]);
        }
        $draft?->ensureSupportedSchema();

        return Inertia::render('weddings/Preview', [
            'accountId' => $account->id,
            'weddingId' => $wedding->id,
            'overviewUrl' => route('accounts.weddings.show', [
                'account' => $account->id,
                'wedding' => $wedding->id,
            ]),
            'wedding' => $wedding->presentationData(),
            'customization' => $draft?->content ?? WebsiteDraft::defaultContent(),
            'heroPhoto' => $draft?->heroPhoto(),
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
        DB::transaction(function () use ($request, $wedding): void {
            $current = $wedding->newQuery()->whereKey($wedding->id)->lockForUpdate()->firstOrFail();
            $this->authorize('update', $current);
            if ($request->has('timezone')) {
                abort_unless(array_key_exists('timezone', $current->getAttributes()), 503);
            }
            $current->update($request->validated());
        }, 3);

        return to_route('accounts.weddings.show', [
            'account' => $account->id,
            'wedding' => $wedding->id,
        ]);
    }

    /** @return array<string, int|string|null> */
    private function weddingData(Wedding $wedding): array
    {
        return [
            ...(array_key_exists('timezone', $wedding->getAttributes()) ? ['timezone' => $wedding->timezone] : []),
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
}
