<?php

namespace App\Policies;

use App\Models\Integration;
use App\Models\User;

class IntegrationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_integrations');
    }

    public function view(User $user, Integration $integration): bool
    {
        return $user->can('manage_integrations');
    }

    public function create(User $user): bool
    {
        return $user->can('manage_integrations');
    }

    public function update(User $user, Integration $integration): bool
    {
        return $user->can('manage_integrations');
    }

    public function delete(User $user, Integration $integration): bool
    {
        return $user->can('manage_integrations');
    }

    public function restore(User $user, Integration $integration): bool
    {
        return $user->can('manage_integrations');
    }

    public function forceDelete(User $user, Integration $integration): bool
    {
        return $user->can('manage_integrations');
    }
}
