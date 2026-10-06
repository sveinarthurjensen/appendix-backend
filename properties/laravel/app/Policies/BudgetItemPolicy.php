<?php

namespace App\Policies;

use App\Models\User;
use App\Models\BudgetItem;

/** Generert fra RLS-blokken til BudgetItem. before() gir admin alt, som i Base44. */
class BudgetItemPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, BudgetItem $model): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, BudgetItem $model): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, BudgetItem $model): bool
    {
        return $user->hasRole('admin');
    }
}
