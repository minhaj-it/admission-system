<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->get('/student')->assertRedirect(route('login'));
    }

    public function test_students_cannot_open_the_admin_area_and_staff_cannot_open_the_student_area(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->staff()->create())->get('/student')->assertForbidden();
    }

    public function test_each_account_type_reaches_its_own_home(): void
    {
        $this->actingAs(User::factory()->staff()->create())->get('/admin')->assertOk()->assertSee('Dashboard');
        $this->actingAs(User::factory()->create())->get('/student')->assertOk();
    }

    public function test_home_page_is_public_with_a_login_button_and_signed_in_users_are_redirected(): void
    {
        $this->get('/')->assertOk()->assertSee(route('login'), false);
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('student.home'));
        $this->actingAs(User::factory()->staff()->create())->get('/')->assertRedirect(route('admin.dashboard'));
    }

    public function test_a_deactivated_account_is_signed_out_on_its_next_request(): void
    {
        $this->actingAs(User::factory()->staff()->inactive()->create())
            ->get('/admin')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
