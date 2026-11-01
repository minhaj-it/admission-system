<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => trim((string) $this->input('username')),
            'mobile' => User::normalizeMobile((string) $this->input('mobile')),
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'regex:/^[A-Za-z0-9._]{3,20}$/', 'unique:users,username'],
            'mobile' => ['required', 'regex:/^01[3-9]\d{8}$/', 'unique:users,mobile'],
            'email' => ['required', 'email:rfc', 'max:191', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Use 3-20 letters, numbers, dots or underscores.',
            'username.unique' => 'This username is already taken.',
            'mobile.regex' => 'Enter an 11-digit mobile number starting with 01.',
            'mobile.unique' => 'This mobile number is already registered.',
            'email.unique' => 'This e-mail is already registered.',
            'password.confirmed' => 'The passwords do not match.',
        ];
    }
}
