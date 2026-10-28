<?php

namespace App\Services;

use App\Models\Account;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CustomerWorkspace
{
    /** @return array{accounts: Collection<int, Account>, wedding: ?Wedding, needsReview: bool, needsOnboarding: bool} */
    public function state(User $user): array
    {
        $accounts = $user->accounts()->wherePivot('role', 'owner')->orderBy('accounts.id')->with('weddings')->get();
        $needsReview = $accounts->count() > 1 || $accounts->contains(fn (Account $account): bool => $account->weddings->count() > 1);
        $wedding = $needsReview ? null : $accounts->first()?->weddings->first();

        return ['accounts' => $accounts, 'wedding' => $wedding, 'needsReview' => $needsReview, 'needsOnboarding' => ! $needsReview && ! $wedding];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{wedding: Wedding, created: bool}
     */
    public function create(User $user, array $attributes, ?Account $target = null): array
    {
        return DB::transaction(function () use ($user, $attributes, $target): array {
            // Every customer creation path uses user -> accounts -> weddings,
            // matching profile deletion and serializing retries before membership exists.
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $accounts = $user->accounts()->wherePivot('role', 'owner')->orderBy('accounts.id')->lockForUpdate()->get();
            if ($target) {
                Gate::forUser($user)->authorize('view', $target);
            }
            abort_if($accounts->count() > 1, 409, 'Your existing workspaces need owner review. No new workspace or wedding was created.');
            $account = $accounts->first();
            if (! $account) {
                abort_if($target !== null, 403);
                $account = Account::create(['name' => Str::limit($user->name, 220, '')."'s Imbitado workspace"]);
                $account->users()->attach($user->id, ['role' => 'owner']);
            }
            abort_if($target && $target->id !== $account->id, 403);
            $weddings = $account->weddings()->orderBy('id')->lockForUpdate()->get();
            abort_if($weddings->count() > 1, 409, 'Your existing weddings need owner review. No new wedding was created.');
            if ($existing = $weddings->first()) {
                abort_if($target !== null, 409, 'This workspace already has its wedding.');

                return ['wedding' => $existing, 'created' => false];
            }
            $base = substr(Str::slug($attributes['partner_one_name'].'-'.$attributes['partner_two_name']) ?: 'wedding', 0, 240);
            do {
                $slug = $base.'-'.Str::lower(Str::random(8));
            } while (Wedding::query()->where('public_slug', $slug)->exists());
            $wedding = $account->weddings()->create([...$attributes, 'public_slug' => $slug]);

            return ['wedding' => $wedding, 'created' => true];
        }, 3);
    }
}
