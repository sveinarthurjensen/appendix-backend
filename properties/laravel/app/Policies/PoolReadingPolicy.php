<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PoolReading;

/** Generert fra RLS-blokken til PoolReading. before() gir admin alt, som i Base44. */
class PoolReadingPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, PoolReading $model): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, PoolReading $model): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, PoolReading $model): bool
    {
        return $user->hasRole('admin');
    }
}
