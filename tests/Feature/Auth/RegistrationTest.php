<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $override = []): array
    {
        return array_merge([
            'username' => 'ayushi.m',
            'mobile' => '01712345678',
            'email' => 'Ayushi@Example.test',
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
        ], $override);
    }

    public function test_registration_page_renders(): void
    {
        $this->get('/register')->assertOk()->assertSee('Create Student Account');
    }

    public function test_registration_creates_an_active_student_with_a_hashed_password(): void
    {
        $this->post('/register', $this->payload())->assertRedirect(route('login'));

        $user = User::where('username', 'ayushi.m')->firstOrFail();
        $this->assertSame('student', $user->account_type);
        $this->assertSame('active', $user->status);
        $this->assertSame('ayushi@example.test', $user->email);
        $this->assertNotSame('Secret1234', $user->password);
        $this->assertTrue(Hash::check('Secret1234', $user->password));
        $this->assertGuest();
    }

    public function test_privileged_fields_sent_by_the_browser_are_ignored(): void
    {
        $this->post('/register', $this->payload(['account_type' => 'staff', 'status' => 'inactive', 'role' => 'super-admin']));

        $user = User::where('username', 'ayushi.m')->firstOrFail();
        $this->assertSame('student', $user->account_type);
        $this->assertSame('active', $user->status);
    }

    public function test_mobile_is_stored_without_the_country_code(): void
    {
        $this->post('/register', $this->payload(['mobile' => '+8801712345678']));

        $this->assertDatabaseHas('users', ['username' => 'ayushi.m', 'mobile' => '01712345678']);
    }

    public function test_duplicates_and_bad_input_are_rejected(): void
    {
        User::factory()->create(['username' => 'taken.name', 'mobile' => '01811111111', 'email' => 'taken@example.test']);

        $this->post('/register', $this->payload(['username' => 'taken.name']))->assertSessionHasErrors('username');
        $this->post('/register', $this->payload(['mobile' => '01811111111']))->assertSessionHasErrors('mobile');
        $this->post('/register', $this->payload(['email' => 'TAKEN@example.test']))->assertSessionHasErrors('email');
        $this->post('/register', $this->payload(['mobile' => '12345']))->assertSessionHasErrors('mobile');
        $this->post('/register', $this->payload(['username' => 'a b']))->assertSessionHasErrors('username');
    }

    public function test_weak_or_unconfirmed_passwords_are_rejected(): void
    {
        $this->post('/register', $this->payload(['password' => 'short1', 'password_confirmation' => 'short1']))->assertSessionHasErrors('password');
        $this->post('/register', $this->payload(['password' => 'onlyletters', 'password_confirmation' => 'onlyletters']))->assertSessionHasErrors('password');
        $this->post('/register', $this->payload(['password_confirmation' => 'Different123']))->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 0);
    }
}
