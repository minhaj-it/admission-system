<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoleManager
{
    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = new Role([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? false),
            ]);
            $role->slug = $this->uniqueSlug($data['name']);
            $role->save();

            AuditLog::record('role.created', $role, null, $role->only(['name', 'slug', 'description', 'is_active']));

            return $role;
        });
    }

    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $old = $role->only(['name', 'description', 'is_active']);

            $role->fill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? false),
            ])->save();

            $new = $role->only(['name', 'description', 'is_active']);

            if ($old !== $new) {
                AuditLog::record('role.updated', $role, $old, $new);
            }

            return $role;
        });
    }

    /** Replaces the role's permission set. The actor may only ADD permissions they hold themselves. */
    public function syncPermissions(User $actor, Role $role, array $keys): void
    {
        $keys = array_values(array_unique($keys));
        $current = $role->permissionKeys();
        $added = array_values(array_diff($keys, $current));
        $removed = array_values(array_diff($current, $keys));

        foreach ($added as $permission) {
            abort_unless($actor->hasPermission($permission), 403);
        }

        if (! $added && ! $removed) {
            return;
        }

        DB::transaction(function () use ($role, $keys, $added, $removed) {
            $role->permissions()->delete();

            if ($keys) {
                RolePermission::insert(array_map(fn ($key) => ['role_id' => $role->id, 'permission' => $key], $keys));
            }

            AuditLog::record('role.permissions_updated', $role, null, ['added' => $added, 'removed' => $removed]);
        });
    }

    public function delete(Role $role): void
    {
        abort_if($role->is_system || $role->is_locked, 403);

        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => 'This user type is assigned to users and cannot be deleted.']);
        }

        DB::transaction(function () use ($role) {
            AuditLog::record('role.deleted', $role, $role->only(['name', 'slug']), null);
            $role->delete();
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name) ?: 'role', 70, '');
        $slug = $base;
        $i = 2;

        while (Role::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
