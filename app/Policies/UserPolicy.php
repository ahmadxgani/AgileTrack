<?php

namespace App\Policies;

use App\Models\User;

/**
 * Authorization for administrative account management.
 *
 * Only administrators may manage accounts; every controller action and the
 * sidebar entry read this single policy.
 */
class UserPolicy
{
    /**
     * Determine whether the user can list accounts.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_administrator;
    }

    /**
     * Determine whether the user can create accounts.
     */
    public function create(User $user): bool
    {
        return $user->is_administrator;
    }

    /**
     * Determine whether the user can update the given account.
     */
    public function update(User $user, User $account): bool
    {
        return $user->is_administrator;
    }
}
