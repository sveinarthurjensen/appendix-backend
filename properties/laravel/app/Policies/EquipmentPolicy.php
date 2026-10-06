<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Equipment;

/** Generert fra RLS-blokken til Equipment. before() gir admin alt, som i Base44. */
class EquipmentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, Equipment $model): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Equipment $model): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Equipment $model): bool
    {
        return $user->hasRole('admin');
    }
}
