<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user) {
            $user = $user->newQuery()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $ownedAccountIds = DB::table('account_members')
                ->where('user_id', $user->getKey())
                ->where('role', 'owner')
                ->orderBy('account_id')
                ->pluck('account_id');

            // Wedding creation locks the account row as well. This makes the
            // ownership check and account deletion decision serialize with it.
            DB::table('accounts')
                ->whereIn('id', $ownedAccountIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $weddingAccountIds = DB::table('weddings')
                ->whereIn('account_id', $ownedAccountIds)
                ->orderBy('account_id')
                ->lockForUpdate()
                ->pluck('account_id')
                ->unique();

            if ($weddingAccountIds->isNotEmpty()) {
                $ownersByAccount = DB::table('account_members')
                    ->whereIn('account_id', $weddingAccountIds)
                    ->where('role', 'owner')
                    ->orderBy('account_id')
                    ->orderBy('user_id')
                    ->lockForUpdate()
                    ->get(['account_id', 'user_id'])
                    ->groupBy('account_id');

                $wouldOrphanWeddingData = $weddingAccountIds->contains(
                    fn ($accountId) => $ownersByAccount->get($accountId, collect())->count() === 1,
                );

                if ($wouldOrphanWeddingData) {
                    throw ValidationException::withMessages([
                        'account_deletion' => 'A wedding workspace must have another owner before your profile can be deleted.',
                    ]);
                }
            }

            $user->delete();
        });

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
