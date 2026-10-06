<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebAuthnChallenge;

/** Generert fra RLS-blokken til WebAuthnChallenge. before() gir admin alt, som i Base44. */
class WebAuthnChallengePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, WebAuthnChallenge $model): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, WebAuthnChallenge $model): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, WebAuthnChallenge $model): bool
    {
        return $user->hasRole('admin');
    }
}
