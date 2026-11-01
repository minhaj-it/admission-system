<?php

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;

trait CreatesStaff
{
    /** Staff user whose role holds exactly the given permission keys. */
    protected function staffWith(array $permissions, array $attributes = []): User
    {
        $role = Role::create([
            'name' => 'Test ' . Str::random(8),
            'slug' => 'test-' . Str::lower(Str::random(8)),
            'is_active' => true,
        ]);

        $role->permissions()->createMany(array_map(fn ($p) => ['permission' => $p], $permissions));

        return User::factory()->staff()->create(array_merge(['role_id' => $role->id], $attributes));
    }

    protected function roleId(string $slug): int
    {
        return (int) Role::where('slug', $slug)->value('id');
    }
}
