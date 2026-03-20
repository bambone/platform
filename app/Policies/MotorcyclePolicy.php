<?php

namespace App\Policies;

use App\Models\Motorcycle;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class MotorcyclePolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $user->can('manage_motorcycles');
    }

    public function view(User $user, Motorcycle $motorcycle): bool
    {
        return $user->can('manage_motorcycles') && $this->userCanAccessTenant($user, $motorcycle);
    }

    public function create(User $user): bool
    {
        return $user->can('manage_motorcycles');
    }

    public function update(User $user, Motorcycle $motorcycle): bool
    {
        return $user->can('manage_motorcycles') && $this->userCanAccessTenant($user, $motorcycle);
    }

    public function delete(User $user, Motorcycle $motorcycle): bool
    {
        return $user->can('manage_motorcycles') && $this->userCanAccessTenant($user, $motorcycle);
    }

    public function restore(User $user, Motorcycle $motorcycle): bool
    {
        return $user->can('manage_motorcycles') && $this->userCanAccessTenant($user, $motorcycle);
    }

    public function forceDelete(User $user, Motorcycle $motorcycle): bool
    {
        return $user->can('manage_motorcycles') && $this->userCanAccessTenant($user, $motorcycle);
    }
}
