<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PortalMessage;

/** Generert fra RLS-blokken til PortalMessage. before() gir admin alt, som i Base44. */
class PortalMessagePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, PortalMessage $model): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, PortalMessage $model): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, PortalMessage $model): bool
    {
        return $user->hasRole('admin');
    }
}
