<?php

namespace App\Policies;

use App\Models\User;
use App\Models\TenantPayment;

/** Generert fra RLS-blokken til TenantPayment. before() gir admin alt, som i Base44. */
class TenantPaymentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, TenantPayment $model): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, TenantPayment $model): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, TenantPayment $model): bool
    {
        return $user->hasRole('admin');
    }
}
