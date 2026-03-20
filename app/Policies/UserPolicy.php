<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_users');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('manage_users');
    }

    public function create(User $user): bool
    {
        return $user->can('manage_users');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('manage_users');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can('manage_users');
    }

    public function restore(User $user, User $model): bool
    {
        return $user->can('manage_users');
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->can('manage_users');
    }
}
