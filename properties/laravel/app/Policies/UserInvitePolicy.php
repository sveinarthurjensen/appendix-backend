<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserInvite;

/** Generert fra RLS-blokken til UserInvite. before() gir admin alt, som i Base44. */
class UserInvitePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, UserInvite $model): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, UserInvite $model): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, UserInvite $model): bool
    {
        return $user->hasRole('admin');
    }
}
