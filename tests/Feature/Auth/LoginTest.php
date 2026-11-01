<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user, array $override = [])
    {
        return $this->post('/login', array_merge([
            'identity' => $user->mobile,
            'password' => 'Password123',
            'portal' => 'student',
        ], $override));
    }

    public function test_login_page_renders_and_sends_security_headers(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Student Login')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_student_can_log_in_with_mobile_email_or_username(): void
    {
        $user = User::factory()->create();

        foreach ([$user->mobile, $user->email, $user->username] as $identity) {
            $this->login($user, ['identity' => $identity])->assertRedirect(route('student.home'));
            $this->assertAuthenticatedAs($user);
            $this->post('/logout');
            $this->assertGuest();
        }
    }

    public function test_mobile_with_country_code_is_accepted(): void
    {
        $user = User::factory()->create(['mobile' => '01712345678']);

        $this->login($user, ['identity' => '+8801712345678'])->assertRedirect(route('student.home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_staff_logs_in_through_admin_tab_and_lands_on_dashboard(): void
    {
        $staff = User::factory()->staff()->create();

        $this->login($staff, ['portal' => 'admin'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($staff);
    }

    public function test_wrong_password_unknown_account_inactive_account_and_wrong_tab_all_fail_the_same_way(): void
    {
        $student = User::factory()->create();
        $staff = User::factory()->staff()->create();
        $inactive = User::factory()->inactive()->create();

        $cases = [
            $this->login($student, ['password' => 'wrong-password']),
            $this->post('/login', ['identity' => '01999999999', 'password' => 'Password123', 'portal' => 'student']),
            $this->login($inactive),
            $this->login($student, ['portal' => 'admin']),
            $this->login($staff, ['portal' => 'student']),
        ];

        foreach ($cases as $response) {
            $response->assertSessionHasErrors(['identity' => 'These credentials do not match our records.']);
            $this->assertGuest();
        }
    }

    public function test_login_is_locked_after_five_failures_even_with_the_right_password(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->login($user, ['password' => 'wrong-password']);
        }

        $this->login($user)->assertSessionHasErrors('identity');
        $this->assertGuest();
        $this->assertDatabaseHas('login_histories', ['event' => 'lockout']);
    }

    public function test_login_and_failures_are_recorded_without_storing_the_full_identity(): void
    {
        $user = User::factory()->create();

        $this->login($user, ['password' => 'wrong-password']);
        $this->assertDatabaseHas('login_histories', ['user_id' => $user->id, 'event' => 'failed']);

        $this->login($user);
        $this->assertDatabaseHas('login_histories', ['user_id' => $user->id, 'event' => 'login']);
        $this->assertDatabaseMissing('login_histories', ['identity' => $user->mobile]);
    }

    public function test_logout_ends_the_session_and_is_recorded(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('login_histories', ['user_id' => $user->id, 'event' => 'logout']);
    }
}
