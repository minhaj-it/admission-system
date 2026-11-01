<?php

namespace App\Http\Requests\Auth;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private static ?string $dummyHash = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identity' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string', 'max:255'],
            'portal' => ['nullable', Rule::in(['admin', 'office', 'student'])],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'identity.required' => 'Enter your mobile number, e-mail or username.',
            'password.required' => 'Enter your password.',
        ];
    }

    /**
     * Every failure (unknown account, wrong password, inactive account, wrong portal tab)
     * returns the same message so the form cannot be used to discover accounts.
     */
    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        $identity = trim((string) $this->input('identity'));
        $user = User::findByIdentity($identity);

        // Always run one hash comparison so response time does not reveal whether the account exists.
        $passwordOk = Hash::check((string) $this->input('password'), $user?->password ?? self::dummyHash());

        if (! ($user && $passwordOk && $user->isActive() && $this->portalMatches($user))) {
            RateLimiter::hit($this->identityKey(), 300);
            RateLimiter::hit($this->ipKey(), 300);
            LoginHistory::record('failed', $user, $identity, $this);

            throw ValidationException::withMessages([
                'identity' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($this->identityKey());

        Auth::login($user, $this->boolean('remember'));

        $user->forceFill(['last_login_at' => now()])->save();
        LoginHistory::record('login', $user, $identity, $this);

        return $user;
    }

    private function portalMatches(User $user): bool
    {
        $portal = $this->input('portal') ?: 'student';

        return $portal === 'student' ? $user->isStudent() : $user->isStaff();
    }

    private function ensureIsNotRateLimited(): void
    {
        $limited = RateLimiter::tooManyAttempts($this->identityKey(), 5)
            || RateLimiter::tooManyAttempts($this->ipKey(), 20);

        if (! $limited) {
            return;
        }

        event(new Lockout($this));
        LoginHistory::record('lockout', null, (string) $this->input('identity'), $this);

        $seconds = max(
            RateLimiter::availableIn($this->identityKey()),
            RateLimiter::availableIn($this->ipKey())
        );

        throw ValidationException::withMessages([
            'identity' => 'Too many login attempts. Try again in ' . max(1, (int) ceil($seconds / 60)) . ' minute(s).',
        ]);
    }

    public function identityKey(): string
    {
        return 'login|' . Str::transliterate(Str::lower(trim((string) $this->input('identity')))) . '|' . $this->ip();
    }

    public function ipKey(): string
    {
        return 'login-ip|' . $this->ip();
    }

    private static function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make(Str::random(32));
    }
}
