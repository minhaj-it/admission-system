<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\CreatesStaff;
use Tests\TestCase;

class PermissionGateTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    public function test_built_in_roles_exist(): void
    {
        foreach (['super-admin', 'admin', 'admission-officer', 'accountant', 'viewer'] as $slug) {
            $this->assertDatabaseHas('roles', ['slug' => $slug]);
        }

        $this->assertTrue((bool) Role::where('slug', 'super-admin')->value('is_locked'));
    }

    public function test_super_admin_has_every_permission(): void
    {
        $super = User::factory()->staff()->create();

        foreach (Permissions::all() as $permission) {
            $this->assertTrue($super->can($permission), $permission);
        }
    }

    public function test_default_roles_get_their_documented_permissions(): void
    {
        $admin = User::factory()->staff('admin')->create();
        $this->assertTrue($admin->can('circular.add'));
        $this->assertTrue($admin->can('payments.refund'));
        $this->assertFalse($admin->can('roles.view'));
        $this->assertFalse($admin->can('users.view'));
        $this->assertFalse($admin->can('audit.view'));

        $officer = User::factory()->staff('admission-officer')->create();
        $this->assertTrue($officer->can('applicant.status'));
        $this->assertFalse($officer->can('payments.refund'));

        $accountant = User::factory()->staff('accountant')->create();
        $this->assertTrue($accountant->can('payments.verify'));
        $this->assertFalse($accountant->can('applicant.status'));

        $viewer = User::factory()->staff('viewer')->create();
        $this->assertTrue($viewer->can('circular.view'));
        $this->assertFalse($viewer->can('circular.add'));
    }

    public function test_no_role_inactive_role_inactive_user_and_students_have_no_permissions(): void
    {
        $this->assertFalse(User::factory()->staff()->create(['role_id' => null])->can('dashboard.view'));
        $this->assertFalse(User::factory()->create()->can('dashboard.view'));
        $this->assertFalse(User::factory()->staff()->inactive()->create()->can('dashboard.view'));

        $user = $this->staffWith(['dashboard.view']);
        $this->assertTrue($user->can('dashboard.view'));

        Role::whereKey($user->role_id)->update(['is_active' => false]);
        $this->assertFalse($user->fresh()->can('dashboard.view'));
    }

    public function test_routes_follow_the_permission_of_the_role(): void
    {
        $viewer = $this->staffWith(['dashboard.view', 'roles.view']);

        $this->actingAs($viewer)->get(route('admin.roles.index'))->assertOk();
        $this->actingAs($viewer)->post(route('admin.roles.store'), ['name' => 'Nope'])->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.audit.index'))->assertForbidden();
    }

    public function test_a_staff_account_without_dashboard_permission_gets_403(): void
    {
        $this->actingAs($this->staffWith(['roles.view']))->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_super_admin_does_not_bypass_record_level_policies(): void
    {
        $super = User::factory()->staff()->create();
        $locked = Role::where('slug', 'super-admin')->first();
        $system = Role::where('slug', 'viewer')->first();

        $this->assertTrue(Gate::forUser($super)->denies('update', $locked));
        $this->assertTrue(Gate::forUser($super)->denies('delete', $locked));
        $this->assertTrue(Gate::forUser($super)->denies('delete', $system));
        $this->assertTrue(Gate::forUser($super)->allows('update', $system));
    }

    public function test_sidebar_only_lists_what_the_user_may_open(): void
    {
        $limited = $this->staffWith(['dashboard.view']);
        $this->actingAs($limited)->get(route('admin.dashboard'))
            ->assertOk()->assertDontSee('User List')->assertDontSee('User Roles')->assertDontSee('Audit Log');

        $super = User::factory()->staff()->create();
        $this->actingAs($super)->get(route('admin.dashboard'))
            ->assertOk()->assertSee('User List')->assertSee('User Roles')->assertSee('Audit Log');
    }
}
