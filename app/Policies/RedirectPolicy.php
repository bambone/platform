<?php

namespace App\Policies;

use App\Models\Redirect;
use App\Models\User;

class RedirectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_seo');
    }

    public function view(User $user, Redirect $redirect): bool
    {
        return $user->can('manage_seo');
    }

    public function create(User $user): bool
    {
        return $user->can('manage_seo');
    }

    public function update(User $user, Redirect $redirect): bool
    {
        return $user->can('manage_seo');
    }

    public function delete(User $user, Redirect $redirect): bool
    {
        return $user->can('manage_seo');
    }

    public function restore(User $user, Redirect $redirect): bool
    {
        return $user->can('manage_seo');
    }

    public function forceDelete(User $user, Redirect $redirect): bool
    {
        return $user->can('manage_seo');
    }
}
