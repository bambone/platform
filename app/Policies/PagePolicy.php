<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_pages') || $user->can('manage_homepage');
    }

    public function view(User $user, Page $page): bool
    {
        return $user->can('manage_pages') || $user->can('manage_homepage');
    }

    public function create(User $user): bool
    {
        return $user->can('manage_pages') || $user->can('manage_homepage');
    }

    public function update(User $user, Page $page): bool
    {
        return $user->can('manage_pages') || $user->can('manage_homepage');
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->can('manage_pages') || $user->can('manage_homepage');
    }

    public function restore(User $user, Page $page): bool
    {
        return $user->can('manage_pages') || $user->can('manage_homepage');
    }

    public function forceDelete(User $user, Page $page): bool
    {
        return $user->can('manage_pages') || $user->can('manage_homepage');
    }
}
