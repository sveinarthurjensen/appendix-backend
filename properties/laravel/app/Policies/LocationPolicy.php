<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Location;

/** Generert fra RLS-blokken til Location. before() gir admin alt, som i Base44. */
class LocationPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, Location $model): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Location $model): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Location $model): bool
    {
        return $user->hasRole('admin');
    }
}
