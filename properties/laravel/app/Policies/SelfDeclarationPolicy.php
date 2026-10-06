<?php

namespace App\Policies;

use App\Models\User;
use App\Models\SelfDeclaration;

/** Generert fra RLS-blokken til SelfDeclaration. before() gir admin alt, som i Base44. */
class SelfDeclarationPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true; // listing filtreres av scopeVisibleTo
    }

    public function view(User $user, SelfDeclaration $model): bool
    {
        return (($model->guest_id === $user->id) || $user->hasRole('admin'));
    }

    public function create(User $user): bool
    {
        return ($user->hasRole('admin') || $user->hasRole('user') || $user->hasRole('guest'));
    }

    public function update(User $user, SelfDeclaration $model): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, SelfDeclaration $model): bool
    {
        return $user->hasRole('admin');
    }
}
