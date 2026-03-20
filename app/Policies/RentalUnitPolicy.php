<?php

namespace App\Policies;

use App\Models\RentalUnit;
use App\Models\User;

class RentalUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_integrations');
    }

    public function view(User $user, RentalUnit $rentalUnit): bool
    {
        return $user->can('manage_integrations');
    }

    public function create(User $user): bool
    {
        return $user->can('manage_integrations');
    }

    public function update(User $user, RentalUnit $rentalUnit): bool
    {
        return $user->can('manage_integrations');
    }

    public function delete(User $user, RentalUnit $rentalUnit): bool
    {
        return $user->can('manage_integrations');
    }

    public function restore(User $user, RentalUnit $rentalUnit): bool
    {
        return $user->can('manage_integrations');
    }

    public function forceDelete(User $user, RentalUnit $rentalUnit): bool
    {
        return $user->can('manage_integrations');
    }
}
