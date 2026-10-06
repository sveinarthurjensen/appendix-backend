<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Property;

/** Generert fra RLS-blokken til Property. before() gir admin alt, som i Base44. */
class PropertyPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false /* ingen regel i Base44 → kun admin via before() */;
    }

    public function view(User $user, Property $model): bool
    {
        return false /* ingen regel i Base44 → kun admin via before() */;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Property $model): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Property $model): bool
    {
        return $user->hasRole('admin');
    }
}
