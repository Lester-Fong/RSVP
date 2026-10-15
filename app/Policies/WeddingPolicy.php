<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Wedding;

class WeddingPolicy
{
    public function view(User $user, Wedding $wedding): bool
    {
        return $wedding->account->users()
            ->whereKey($user->getKey())
            ->wherePivot('role', 'owner')
            ->exists();
    }

    public function update(User $user, Wedding $wedding): bool
    {
        return $this->view($user, $wedding);
    }
}
