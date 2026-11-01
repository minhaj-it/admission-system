<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_render(): void
    {
        $this->get('/forgot-password')->assertOk();
        $this->get('/reset-password/some-token')->assertOk();
    }

    public function test_reset_link_is_sent_and_the_answer_is_identical_for_unknown_emails(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->post('/forgot-password', ['email' => $user->email]);
        $unknown = $this->post('/forgot-password', ['email' => 'nobody@example.test']);

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
        $known->assertSessionHas('status');
        $known->assertSessionHas('status', 'If that e-mail is registered, a reset link has been sent.');
        $unknown->assertSessionHas('status', 'If that e-mail is registered, a reset link has been sent.');
        $unknown->assertSessionHasNoErrors();
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassw0rd1',
            'password_confirmation' => 'NewPassw0rd1',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewPassw0rd1', $user->fresh()->password));
        $this->assertDatabaseHas('login_histories', ['user_id' => $user->id, 'event' => 'password_reset']);
    }

    public function test_an_invalid_token_is_rejected_and_the_password_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'NewPassw0rd1',
            'password_confirmation' => 'NewPassw0rd1',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('Password123', $user->fresh()->password));
    }
}
