<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    /**
     * The Account/Transaction models already carry a `user_isolation` global scope,
     * so route-model-binding alone will 404 a cross-tenant record in normal use.
     * These policies are the explicit, second layer of defense the audit called for:
     * they still hold even if a future code path queries via withoutGlobalScope(),
     * a queued job, or an Artisan command with no authenticated user in context.
     */
    public function view(User $user, Account $account): bool
    {
        return $account->user_id === $user->id;
    }

    public function update(User $user, Account $account): bool
    {
        return $account->user_id === $user->id;
    }

    public function delete(User $user, Account $account): bool
    {
        return $account->user_id === $user->id;
    }
}
