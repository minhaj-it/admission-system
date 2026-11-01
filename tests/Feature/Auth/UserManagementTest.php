<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesStaff;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = User::factory()->staff()->create(['name' => 'Chief Admin']);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'New Officer',
            'username' => 'new.officer',
            'email' => 'officer@example.test',
            'mobile' => '01711111111',
            'role_id' => $this->roleId('admission-officer'),
            'is_active' => 1,
            'password' => 'Secret1234',
        ], $override);
    }

    private function fakeSession(User $user, string $id = 'sess-1'): void
    {
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'test', 'payload' => 'x', 'last_activity' => time(),
        ]);
    }

    public function test_list_shows_staff_only_and_supports_search(): void
    {
        User::factory()->staff('viewer')->create(['name' => 'Zed Staffer']);
        User::factory()->staff('viewer')->create(['name' => 'Other Colleague']);
        User::factory()->create(['name' => 'Student Person']);

        $this->actingAs($this->super)->get(route('admin.users.index'))
            ->assertOk()->assertSee('Zed Staffer')->assertSee('Other Colleague')->assertDontSee('Student Person');

        // the signed-in user's own name is always in the top bar, so the filter is checked with another staff member
        $this->actingAs($this->super)->get(route('admin.users.index', ['q' => 'Zed']))
            ->assertOk()->assertSee('Zed Staffer')->assertDontSee('Other Colleague');
    }

    public function test_super_admin_creates_a_staff_user_with_a_hashed_password_and_an_audit_entry(): void
    {
        $this->actingAs($this->super)->post(route('admin.users.store'), $this->payload())
            ->assertRedirect(route('admin.users.index'));

        $user = User::where('username', 'new.officer')->firstOrFail();
        $this->assertSame('staff', $user->account_type);
        $this->assertSame('active', $user->status);
        $this->assertSame($this->roleId('admission-officer'), $user->role_id);
        $this->assertTrue(Hash::check('Secret1234', $user->password));

        $log = AuditLog::where('action', 'user.created')->firstOrFail();
        $this->assertArrayNotHasKey('password', $log->new_values);
        $this->assertStringNotContainsString('Secret1234', $log->toJson());
    }

    public function test_account_type_and_status_cannot_be_forced_from_the_browser(): void
    {
        $this->actingAs($this->super)->post(route('admin.users.store'), $this->payload(['account_type' => 'student', 'status' => 'inactive']));

        $user = User::where('username', 'new.officer')->firstOrFail();
        $this->assertSame('staff', $user->account_type);
        $this->assertSame('active', $user->status);
    }

    public function test_validation_rejects_bad_input(): void
    {
        User::factory()->create(['username' => 'taken.name', 'mobile' => '01811111111', 'email' => 'taken@example.test']);
        $actor = $this->actingAs($this->super);

        $actor->post(route('admin.users.store'), $this->payload(['username' => 'taken.name']))->assertSessionHasErrors('username');
        $actor->post(route('admin.users.store'), $this->payload(['mobile' => '01811111111']))->assertSessionHasErrors('mobile');
        $actor->post(route('admin.users.store'), $this->payload(['email' => 'TAKEN@example.test']))->assertSessionHasErrors('email');
        $actor->post(route('admin.users.store'), $this->payload(['mobile' => '123']))->assertSessionHasErrors('mobile');
        $actor->post(route('admin.users.store'), $this->payload(['password' => 'weak']))->assertSessionHasErrors('password');
        $actor->post(route('admin.users.store'), $this->payload(['password' => '']))->assertSessionHasErrors('password');
        $actor->post(route('admin.users.store'), $this->payload(['role_id' => 99999]))->assertSessionHasErrors('role_id');
    }

    public function test_only_roles_the_actor_could_hold_themselves_can_be_assigned(): void
    {
        $actor = $this->staffWith(['users.view', 'users.add']);

        $this->actingAs($actor)->post(route('admin.users.store'), $this->payload(['role_id' => $this->roleId('super-admin')]))->assertForbidden();
        $this->actingAs($actor)->post(route('admin.users.store'), $this->payload(['role_id' => $this->roleId('admin')]))->assertForbidden();
        $this->assertDatabaseMissing('users', ['username' => 'new.officer']);

        $rich = $this->staffWith(['users.view', 'users.add', 'dashboard.view', 'circular.view', 'applicant-list.view', 'payments.view', 'registered-accounts.view']);
        $this->actingAs($rich)->post(route('admin.users.store'), $this->payload(['role_id' => $this->roleId('viewer')]))
            ->assertRedirect(route('admin.users.index'));
    }

    public function test_update_changes_only_what_changed_and_audits_it(): void
    {
        $target = User::factory()->staff('viewer')->create(['name' => 'Old Name']);

        $this->actingAs($this->super)->put(route('admin.users.update', $target), $this->payload([
            'name' => 'Fresh Name', 'username' => $target->username, 'email' => $target->email, 'mobile' => $target->mobile,
            'role_id' => $target->role_id, 'password' => '',
        ]))->assertRedirect(route('admin.users.index'));

        $this->assertSame('Fresh Name', $target->fresh()->name);
        $log = AuditLog::where('action', 'user.updated')->firstOrFail();
        $this->assertSame(['name' => 'Fresh Name'], $log->new_values);
        $this->assertSame(['name' => 'Old Name'], $log->old_values);
    }

    public function test_changing_role_or_password_ends_the_users_sessions(): void
    {
        $target = User::factory()->staff('viewer')->create();
        $this->fakeSession($target);

        $this->actingAs($this->super)->put(route('admin.users.update', $target), $this->payload([
            'name' => $target->name, 'username' => $target->username, 'email' => $target->email, 'mobile' => $target->mobile,
            'role_id' => $this->roleId('accountant'), 'password' => 'NewPassw0rd1',
        ]))->assertRedirect(route('admin.users.index'));

        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
        $fresh = $target->fresh();
        $this->assertTrue(Hash::check('NewPassw0rd1', $fresh->password));
        $this->assertSame($this->roleId('accountant'), $fresh->role_id);

        $log = AuditLog::where('action', 'user.updated')->firstOrFail();
        $this->assertTrue($log->new_values['password_changed']);
        $this->assertStringNotContainsString('NewPassw0rd1', $log->toJson());
        $this->assertStringNotContainsString($fresh->password, $log->toJson());
    }

    public function test_deactivation_signs_the_user_out_everywhere_and_activation_restores_access(): void
    {
        $target = User::factory()->staff('viewer')->create();
        $this->fakeSession($target);

        $this->actingAs($this->super)->patch(route('admin.users.deactivate', $target))->assertRedirect(route('admin.users.index'));
        $this->assertSame('inactive', $target->fresh()->status);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.deactivated', 'subject_id' => $target->id]);

        $this->actingAs($this->super)->patch(route('admin.users.activate', $target))->assertRedirect(route('admin.users.index'));
        $this->assertSame('active', $target->fresh()->status);
    }

    public function test_nobody_can_deactivate_or_demote_themselves(): void
    {
        $this->actingAs($this->super)->patch(route('admin.users.deactivate', $this->super))->assertSessionHasErrors('status');
        $this->assertSame('active', $this->super->fresh()->status);

        $this->actingAs($this->super)->put(route('admin.users.update', $this->super), $this->payload([
            'name' => $this->super->name, 'username' => $this->super->username, 'email' => $this->super->email,
            'mobile' => $this->super->mobile, 'role_id' => $this->roleId('viewer'), 'password' => '',
        ]))->assertSessionHasErrors('role_id');
        $this->assertSame($this->roleId('super-admin'), $this->super->fresh()->role_id);
    }

    public function test_a_manager_cannot_touch_accounts_that_hold_more_power_than_they_do(): void
    {
        $manager = $this->staffWith(['users.view', 'users.edit']);
        $boss = User::factory()->staff()->create();                // Super Admin
        $admin = User::factory()->staff('admin')->create();        // far more permissions than $manager

        foreach ([$boss, $admin] as $target) {
            $this->actingAs($manager)->put(route('admin.users.update', $target), $this->payload([
                'name' => 'Hacked', 'username' => $target->username, 'email' => $target->email, 'mobile' => $target->mobile,
                'role_id' => $target->role_id, 'password' => 'Takeover123',
            ]))->assertForbidden();
            $this->actingAs($manager)->patch(route('admin.users.deactivate', $target))->assertForbidden();
        }

        $this->assertNotSame('Hacked', $boss->fresh()->name);
    }

    public function test_student_accounts_are_not_editable_here(): void
    {
        $student = User::factory()->create();

        $this->actingAs($this->super)->patch(route('admin.users.deactivate', $student))->assertForbidden();
        $this->assertSame('active', $student->fresh()->status);
    }

    public function test_read_only_staff_see_the_list_but_cannot_change_anything(): void
    {
        $reader = $this->staffWith(['users.view']);
        $target = User::factory()->staff('viewer')->create();

        $this->actingAs($reader)->get(route('admin.users.index'))->assertOk()->assertSee('view the list only');
        $this->actingAs($reader)->post(route('admin.users.store'), $this->payload())->assertForbidden();
        $this->actingAs($reader)->patch(route('admin.users.deactivate', $target))->assertForbidden();
    }
}
