<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ParkingRental;

/** Generert fra RLS-blokken til ParkingRental. before() gir admin alt, som i Base44. */
class ParkingRentalPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, ParkingRental $model): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, ParkingRental $model): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, ParkingRental $model): bool
    {
        return $user->hasRole('admin');
    }
}
