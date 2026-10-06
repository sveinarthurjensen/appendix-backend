<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Contract;

/** Generert fra RLS-blokken til Contract. before() gir admin alt, som i Base44. */
class ContractPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, Contract $model): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return ($user->hasRole('admin') || $user->hasRole('user') || $user->hasRole('guest'));
    }

    public function update(User $user, Contract $model): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Contract $model): bool
    {
        return $user->hasRole('admin');
    }
}
