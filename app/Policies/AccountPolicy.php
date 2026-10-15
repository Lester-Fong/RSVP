<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    public function view(User $user, Account $account): bool
    {
        return $account->users()
            ->whereKey($user->getKey())
            ->wherePivot('role', 'owner')
            ->exists();
    }

    public function createWedding(User $user, Account $account): bool
    {
        return $this->view($user, $account);
    }
}
