<?php

namespace App\Policies;

use App\Models\Faq;
use App\Models\User;

class FaqPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_faq');
    }

    public function view(User $user, Faq $faq): bool
    {
        return $user->can('manage_faq');
    }

    public function create(User $user): bool
    {
        return $user->can('manage_faq');
    }

    public function update(User $user, Faq $faq): bool
    {
        return $user->can('manage_faq');
    }

    public function delete(User $user, Faq $faq): bool
    {
        return $user->can('manage_faq');
    }

    public function restore(User $user, Faq $faq): bool
    {
        return $user->can('manage_faq');
    }

    public function forceDelete(User $user, Faq $faq): bool
    {
        return $user->can('manage_faq');
    }
}
