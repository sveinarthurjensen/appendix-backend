<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ChatMessage;

/** Generert fra RLS-blokken til ChatMessage. before() gir admin alt, som i Base44. */
class ChatMessagePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, ChatMessage $model): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, ChatMessage $model): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, ChatMessage $model): bool
    {
        return $user->hasRole('admin');
    }
}
