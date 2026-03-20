<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class BookingPolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $user->can('manage_bookings');
    }

    public function view(User $user, Booking $booking): bool
    {
        return $user->can('manage_bookings') && $this->userCanAccessTenant($user, $booking);
    }

    public function create(User $user): bool
    {
        return $user->can('manage_bookings');
    }

    public function update(User $user, Booking $booking): bool
    {
        return $user->can('manage_bookings') && $this->userCanAccessTenant($user, $booking);
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $user->can('manage_bookings') && $this->userCanAccessTenant($user, $booking);
    }

    public function restore(User $user, Booking $booking): bool
    {
        return $user->can('manage_bookings') && $this->userCanAccessTenant($user, $booking);
    }

    public function forceDelete(User $user, Booking $booking): bool
    {
        return $user->can('manage_bookings') && $this->userCanAccessTenant($user, $booking);
    }
}
