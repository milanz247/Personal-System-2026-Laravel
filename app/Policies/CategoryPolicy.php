<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CategoryPolicy
{
    /**
     * Shared/system categories (user_id null) are visible to everyone but owned by no one.
     */
    public function view(User $user, Category $category): bool
    {
        return $category->user_id === null || $category->user_id === $user->id;
    }

    public function update(User $user, Category $category): Response
    {
        if ($category->user_id === null) {
            return Response::deny('This is a shared category and cannot be modified.');
        }

        return $category->user_id === $user->id
            ? Response::allow()
            : Response::deny('You can only modify your own categories.');
    }

    public function delete(User $user, Category $category): Response
    {
        return $this->update($user, $category);
    }
}
