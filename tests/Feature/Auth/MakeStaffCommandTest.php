<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MakeStaffCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_active_super_admin_by_default(): void
    {
        $this->artisan('admission:make-staff', ['email' => 'Office@Example.test', '--username' => 'office.one', '--name' => 'Office One'])
            ->expectsQuestion('Password', 'Secret1234')
            ->expectsQuestion('Confirm password', 'Secret1234')
            ->assertSuccessful();

        $user = User::where('email', 'office@example.test')->firstOrFail();
        $this->assertSame('staff', $user->account_type);
        $this->assertSame('active', $user->status);
        $this->assertSame('Office One', $user->name);
        $this->assertTrue($user->hasSuperAdminRole());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created', 'subject_id' => $user->id]);
    }

    public function test_it_accepts_another_role(): void
    {
        $this->artisan('admission:make-staff', ['email' => 'viewer@example.test', '--role' => 'viewer'])
            ->expectsQuestion('Password', 'Secret1234')
            ->expectsQuestion('Confirm password', 'Secret1234')
            ->assertSuccessful();

        $this->assertSame('viewer', User::where('email', 'viewer@example.test')->first()->role->slug);
    }

    public function test_it_refuses_an_unknown_role_and_mismatched_passwords(): void
    {
        $this->artisan('admission:make-staff', ['email' => 'a@example.test', '--role' => 'nope'])->assertFailed();

        $this->artisan('admission:make-staff', ['email' => 'office@example.test'])
            ->expectsQuestion('Password', 'Secret1234')
            ->expectsQuestion('Confirm password', 'Other1234')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_assign_role_updates_an_existing_staff_account(): void
    {
        $user = User::factory()->staff('viewer')->create();

        $this->artisan('admission:assign-role', ['email' => $user->email, 'role' => 'admin'])->assertSuccessful();

        $this->assertSame('admin', $user->fresh()->role->slug);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.role_assigned', 'subject_id' => $user->id]);

        $this->artisan('admission:assign-role', ['email' => 'nobody@example.test', 'role' => 'admin'])->assertFailed();
        $this->artisan('admission:assign-role', ['email' => $user->email, 'role' => 'nope'])->assertFailed();
    }
}
