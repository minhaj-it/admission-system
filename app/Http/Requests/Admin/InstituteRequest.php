<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstituteRequest extends FormRequest
{
    public const FILE_TYPES = ['pdf', 'jpg', 'png', 'webp'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $website = trim((string) $this->input('website'));

        if ($website !== '' && ! preg_match('#^https?://#i', $website)) {
            $website = 'https://' . $website;
        }

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'phone' => trim((string) $this->input('phone')),
            'email' => trim((string) $this->input('email')),
            'website' => $website,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'short_name' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:300'],
            'phone' => ['nullable', 'regex:/^[+\d][\d\s-]*$/', function ($attribute, $value, $fail) {
                $digits = strlen(preg_replace('/\D/', '', (string) $value));
                if ($digits < 7 || $digits > 15) {
                    $fail('Enter a valid phone number (7 to 15 digits).');
                }
            }],
            'email' => ['nullable', 'email:rfc', 'max:191'],
            'website' => ['nullable', 'url:http,https', 'max:191', 'regex:/^https?:\/\/[^\/]+\.[^\/]+/i'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024'], // SVG is deliberately not accepted
            'remove_logo' => ['nullable', 'boolean'],
            'image_max_kb' => ['required', 'integer', 'between:50,2048'],
            'file_max_kb' => ['required', 'integer', 'between:100,5120'],
            'file_types' => ['required', 'array', 'min:1'],
            'file_types.*' => ['string', Rule::in(self::FILE_TYPES)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Institute name is required.',
            'phone.regex' => 'Enter a valid phone number (digits, +, - and spaces; 7 to 15 digits).',
            'email.email' => 'Enter a valid email address.',
            'website.url' => 'Enter a valid web address.',
            'website.regex' => 'Enter a valid web address.',
            'logo.mimes' => 'Logo must be a PNG, JPG or WebP image.',
            'logo.max' => 'Logo must be 1 MB or smaller.',
            'file_types.required' => 'Select at least one document format.',
        ];
    }
}
