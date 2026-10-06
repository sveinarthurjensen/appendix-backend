<?php

namespace App\Policies;

use App\Models\User;
use App\Models\DataBackup;

/** Generert fra RLS-blokken til DataBackup. before() gir admin alt, som i Base44. */
class DataBackupPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, DataBackup $model): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, DataBackup $model): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, DataBackup $model): bool
    {
        return $user->hasRole('admin');
    }
}
