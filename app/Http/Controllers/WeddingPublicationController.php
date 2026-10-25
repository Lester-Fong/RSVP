<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublishWeddingRequest;
use App\Http\Requests\UnpublishWeddingRequest;
use App\Models\Account;
use App\Models\Wedding;
use App\Services\WeddingPublication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class WeddingPublicationController extends Controller
{
    public function publish(PublishWeddingRequest $request, Account $account, Wedding $wedding, WeddingPublication $publication): RedirectResponse|JsonResponse
    {
        $publication->publish($wedding, $request->validated(), $request->user());

        if ($request->expectsJson()) {
            return response()->json(['publication' => $publication->summary($wedding->fresh())]);
        }

        return to_route('accounts.weddings.show', [$account, $wedding]);
    }

    public function unpublish(UnpublishWeddingRequest $request, Account $account, Wedding $wedding, WeddingPublication $publication): RedirectResponse
    {
        $publication->unpublish($wedding, (int) $request->validated('publication_version'), $request->user());

        return to_route('accounts.weddings.show', [$account, $wedding]);
    }
}
