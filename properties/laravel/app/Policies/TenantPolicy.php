<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Tenant;

/** Generert fra RLS-blokken til Tenant. before() gir admin alt, som i Base44. */
class TenantPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true; // listing filtreres av scopeVisibleTo
    }

    public function view(User $user, Tenant $model): bool
    {
        return (($model->email === $user->email) || $user->hasRole('admin'));
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Tenant $model): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Tenant $model): bool
    {
        return $user->hasRole('admin');
    }
}
