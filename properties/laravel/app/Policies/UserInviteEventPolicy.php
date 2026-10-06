<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserInviteEvent;

/** Generert fra RLS-blokken til UserInviteEvent. before() gir admin alt, som i Base44. */
class UserInviteEventPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, UserInviteEvent $model): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, UserInviteEvent $model): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, UserInviteEvent $model): bool
    {
        return $user->hasRole('admin');
    }
}
