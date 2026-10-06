<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Invitation;

/** Generert fra RLS-blokken til Invitation. before() gir admin alt, som i Base44. */
class InvitationPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, Invitation $model): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Invitation $model): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Invitation $model): bool
    {
        return $user->hasRole('admin');
    }
}
