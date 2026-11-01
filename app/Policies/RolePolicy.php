<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/** Record-level rules. The permission itself (roles.edit ...) is enforced by the route middleware. */
class RolePolicy
{
    public function update(User $user, Role $role): bool
    {
        return ! $role->is_locked;
    }

    public function permissions(User $user, Role $role): bool
    {
        return ! $role->is_locked;
    }

    public function delete(User $user, Role $role): bool
    {
        return ! $role->is_locked && ! $role->is_system;
    }
}
