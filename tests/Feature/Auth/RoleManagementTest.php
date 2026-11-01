<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStaff;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = User::factory()->staff()->create();
    }

    public function test_page_renders_with_types_and_access_matrix(): void
    {
        $this->actingAs($this->super)->get(route('admin.roles.index'))
            ->assertOk()->assertSee('Super Admin')->assertSee('User Access')->assertSee('Special permissions');

        $role = Role::where('slug', 'viewer')->first();
        $this->actingAs($this->super)->get(route('admin.roles.index', ['role' => $role->id, 'tab' => 'access']))->assertOk();
    }

    public function test_super_admin_creates_a_role_and_it_is_audited(): void
    {
        $this->actingAs($this->super)
            ->post(route('admin.roles.store'), ['name' => 'Data Entry', 'description' => 'Enters data', 'is_active' => 1])
            ->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseHas('roles', ['slug' => 'data-entry', 'name' => 'Data Entry', 'is_system' => false, 'is_locked' => false]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.created', 'actor_id' => $this->super->id]);
    }

    public function test_duplicate_or_empty_names_are_rejected_and_system_flags_cannot_be_injected(): void
    {
        $this->actingAs($this->super)->post(route('admin.roles.store'), ['name' => 'Admin'])->assertSessionHasErrors('name');
        $this->actingAs($this->super)->post(route('admin.roles.store'), ['name' => ''])->assertSessionHasErrors('name');

        $this->actingAs($this->super)->post(route('admin.roles.store'), ['name' => 'Sneaky', 'is_system' => 1, 'is_locked' => 1]);
        $this->assertDatabaseHas('roles', ['slug' => 'sneaky', 'is_system' => false, 'is_locked' => false]);
    }

    public function test_a_role_can_be_edited_but_the_slug_stays(): void
    {
        $role = Role::where('slug', 'viewer')->first();

        $this->actingAs($this->super)
            ->put(route('admin.roles.update', $role), ['name' => 'Read Only', 'description' => 'New text', 'is_active' => 1])
            ->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'slug' => 'viewer', 'name' => 'Read Only']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.updated']);
    }

    public function test_the_locked_super_admin_role_cannot_be_changed_in_any_way(): void
    {
        $locked = Role::where('slug', 'super-admin')->first();

        $this->actingAs($this->super)->put(route('admin.roles.update', $locked), ['name' => 'Renamed'])->assertForbidden();
        $this->actingAs($this->super)->put(route('admin.roles.permissions', $locked), ['permissions' => ['dashboard.view']])->assertForbidden();
        $this->actingAs($this->super)->delete(route('admin.roles.destroy', $locked))->assertForbidden();

        $this->assertDatabaseHas('roles', ['slug' => 'super-admin', 'name' => 'Super Admin']);
    }

    public function test_permissions_are_saved_validated_and_audited(): void
    {
        $role = Role::where('slug', 'viewer')->first();

        $this->actingAs($this->super)
            ->put(route('admin.roles.permissions', $role), ['permissions' => ['dashboard.view', 'circular.add']])
            ->assertRedirect(route('admin.roles.index', ['role' => $role->id, 'tab' => 'access']));

        $this->assertEqualsCanonicalizing(['dashboard.view', 'circular.add'], $role->permissionKeys());
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.permissions_updated', 'subject_id' => $role->id]);

        $this->actingAs($this->super)
            ->put(route('admin.roles.permissions', $role), ['permissions' => ['hack.everything']])
            ->assertSessionHasErrors('permissions.0');

        $this->actingAs($this->super)->put(route('admin.roles.permissions', $role), [])->assertRedirect();
        $this->assertSame([], $role->permissionKeys());
    }

    public function test_an_actor_cannot_grant_a_permission_they_do_not_hold(): void
    {
        $manager = $this->staffWith(['dashboard.view', 'roles.view', 'roles.edit']);
        $role = Role::where('slug', 'viewer')->first();

        $this->actingAs($manager)
            ->put(route('admin.roles.permissions', $role), ['permissions' => ['dashboard.view', 'users.del']])
            ->assertForbidden();

        $this->assertNotContains('users.del', $role->permissionKeys());

        $this->actingAs($manager)
            ->put(route('admin.roles.permissions', $role), ['permissions' => ['dashboard.view']])
            ->assertRedirect();
    }

    public function test_system_roles_and_roles_in_use_cannot_be_deleted_but_unused_custom_roles_can(): void
    {
        $this->actingAs($this->super)->delete(route('admin.roles.destroy', Role::where('slug', 'viewer')->first()))->assertForbidden();

        $inUse = $this->staffWith(['dashboard.view']);
        $this->actingAs($this->super)->delete(route('admin.roles.destroy', $inUse->role))->assertSessionHasErrors('role');
        $this->assertDatabaseHas('roles', ['id' => $inUse->role_id]);

        $unused = Role::create(['name' => 'Temporary', 'slug' => 'temporary', 'is_active' => true]);
        $this->actingAs($this->super)->delete(route('admin.roles.destroy', $unused))->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseMissing('roles', ['slug' => 'temporary']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.deleted']);
    }

    public function test_students_cannot_reach_role_management(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.roles.index'))->assertForbidden();
    }
}
