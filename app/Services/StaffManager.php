<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\Sessions;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffManager
{
    private const FIELDS = ['name', 'username', 'email', 'mobile'];

    /** Roles the actor is allowed to hand out (active, and not more powerful than the actor). */
    public function assignableRoles(User $actor): Collection
    {
        return Role::where('is_active', true)->orderBy('name')->get()
            ->filter(fn (Role $role) => $this->canAssign($actor, $role))
            ->values();
    }

    public function create(User $actor, array $data): User
    {
        $role = Role::findOrFail($data['role_id']);
        $this->assertAssignable($actor, $role);

        $user = new User(Arr::only($data, [...self::FIELDS, 'password']));
        $user->account_type = User::TYPE_STAFF;
        $user->status = ! empty($data['is_active']) ? User::STATUS_ACTIVE : User::STATUS_INACTIVE;
        $user->role_id = $role->id;

        DB::transaction(function () use ($user, $role) {
            $user->save();

            AuditLog::record('user.created', $user, null, [
                'name' => $user->name, 'username' => $user->username, 'email' => $user->email,
                'mobile' => $user->mobile, 'role' => $role->slug, 'status' => $user->status,
            ]);
        });

        return $user;
    }

    public function update(User $actor, User $target, array $data): User
    {
        $role = Role::findOrFail($data['role_id']);
        $roleChanged = $role->id !== $target->role_id;
        $active = ! empty($data['is_active']);
        $passwordChanged = ! empty($data['password']);

        if ($roleChanged) {
            $this->assertAssignable($actor, $role);
        }

        if ($actor->is($target) && ($roleChanged || ! $active)) {
            throw ValidationException::withMessages(['role_id' => 'You cannot change your own user type or deactivate your own account.']);
        }

        $this->guardLastSuperAdmin($target, $role, $active);

        $old = $this->snapshot($target);

        $target->fill(Arr::only($data, self::FIELDS));
        $target->role_id = $role->id;
        $target->status = $active ? User::STATUS_ACTIVE : User::STATUS_INACTIVE;

        if ($passwordChanged) {
            $target->password = $data['password'];
        }

        DB::transaction(function () use ($target, $role, $old, $passwordChanged) {
            $target->save();
            $target->setRelation('role', $role);

            $new = $this->snapshot($target);
            $changedOld = array_diff_assoc($old, $new);
            $changedNew = array_diff_assoc($new, $old);

            if ($passwordChanged) {
                $changedNew['password_changed'] = true;
            }

            if ($changedOld || $changedNew) {
                AuditLog::record('user.updated', $target, $changedOld, $changedNew);
            }
        });

        if ($roleChanged || ! $active || $passwordChanged) {
            Sessions::revokeFor($target, $actor->is($target) ? session()->getId() : null);
        }

        return $target;
    }

    public function setActive(User $actor, User $target, bool $active): void
    {
        if ($actor->is($target) && ! $active) {
            throw ValidationException::withMessages(['status' => 'You cannot deactivate your own account.']);
        }

        $this->guardLastSuperAdmin($target, $target->role, $active);

        if ($target->isActive() === $active) {
            return;
        }

        DB::transaction(function () use ($target, $active) {
            $old = ['status' => $target->status];
            $target->status = $active ? User::STATUS_ACTIVE : User::STATUS_INACTIVE;
            $target->save();

            AuditLog::record($active ? 'user.activated' : 'user.deactivated', $target, $old, ['status' => $target->status]);
        });

        if (! $active) {
            Sessions::revokeFor($target);
        }
    }

    private function canAssign(User $actor, Role $role): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        return ! $role->is_locked
            && collect($role->permissionKeys())->every(fn ($permission) => $actor->hasPermission($permission));
    }

    private function assertAssignable(User $actor, Role $role): void
    {
        if (! $role->is_active) {
            throw ValidationException::withMessages(['role_id' => 'This user type is not active.']);
        }

        abort_unless($this->canAssign($actor, $role), 403);
    }

    /** The system must always keep at least one active Super Admin. */
    private function guardLastSuperAdmin(User $target, ?Role $newRole, bool $newActive): void
    {
        $staysSuperAdmin = $newActive && $newRole?->slug === Role::SUPER_ADMIN;

        if ($target->isSuperAdmin() && ! $staysSuperAdmin && User::activeSuperAdminCount() <= 1) {
            throw ValidationException::withMessages(['role_id' => 'At least one active Super Admin is required.']);
        }
    }

    private function snapshot(User $user): array
    {
        return [
            'name' => $user->name, 'username' => $user->username, 'email' => $user->email,
            'mobile' => $user->mobile, 'role' => $user->role?->slug, 'status' => $user->status,
        ];
    }
}
