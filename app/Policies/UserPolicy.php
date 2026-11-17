<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('users.manage');
    }

    public function updateRole(User $actor, User $target): bool
    {
        return $actor->id !== $target->id
            && $actor->hasPermission('roles.manage')
            && (! $target->hasRole('super_admin') || $actor->hasRole('super_admin'));
    }
}
