<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'username' => trim((string) $this->input('username')),
            'mobile' => User::normalizeMobile((string) $this->input('mobile')),
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'regex:/^[A-Za-z0-9._]{3,20}$/', Rule::unique('users', 'username')->ignore($user)],
            'email' => ['required', 'email:rfc', 'max:191', Rule::unique('users', 'email')->ignore($user)],
            'mobile' => ['required', 'regex:/^01[3-9]\d{8}$/', Rule::unique('users', 'mobile')->ignore($user)],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'is_active' => ['nullable', 'boolean'],
            'password' => [$user ? 'nullable' : 'required', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'username.regex' => 'Use 3-20 letters, numbers, dots or underscores.',
            'username.unique' => 'This username is already taken.',
            'email.unique' => 'This e-mail is already used.',
            'mobile.regex' => 'Enter an 11-digit mobile number starting with 01.',
            'mobile.unique' => 'This mobile number is already used.',
            'role_id.required' => 'Select a user type.',
        ];
    }
}
