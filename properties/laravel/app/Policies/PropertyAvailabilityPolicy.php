<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PropertyAvailability;

/** Generert fra RLS-blokken til PropertyAvailability. before() gir admin alt, som i Base44. */
class PropertyAvailabilityPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false /* ingen regel i Base44 → kun admin via before() */;
    }

    public function view(User $user, PropertyAvailability $model): bool
    {
        return false /* ingen regel i Base44 → kun admin via before() */;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, PropertyAvailability $model): bool
    {
        return ($user->hasRole('admin') || $user->hasRole('guest'));
    }

    public function delete(User $user, PropertyAvailability $model): bool
    {
        return $user->hasRole('admin');
    }
}
