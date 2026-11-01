<?php

namespace App\Http\Requests\Admin;

use App\Models\AdmissionCircular;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CircularRequest extends FormRequest
{
    private const YOUTUBE_HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'www.youtube-nocookie.com', 'youtube-nocookie.com'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'circular_url' => trim((string) $this->input('circular_url')),
            'video_url' => trim((string) $this->input('video_url')),
            'portal_host' => strtolower(trim((string) $this->input('portal_host'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'type' => [$this->route('circular') ? 'nullable' : 'required', Rule::in(array_keys(AdmissionCircular::TYPES))],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'circular_url' => ['required', 'url:http,https', 'max:500'],
            'video_url' => ['nullable', 'url:http,https', 'max:300', function ($attribute, $value, $fail) {
                $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
                if (! in_array($host, self::YOUTUBE_HOSTS, true)) {
                    $fail('Use a YouTube link.');
                }
            }],
            'portal_host' => ['required', 'regex:/^(?:[a-z0-9-]+\.)+[a-z]{2,}$/'],
            'unique_birth_id' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Circular name is required.',
            'opens_at.required' => 'Open date and time is required.',
            'closes_at.required' => 'Close date and time is required.',
            'closes_at.after' => 'Close date must be after the open date.',
            'circular_url.required' => 'Circular URL is required.',
            'circular_url.url' => 'Enter a valid http or https link.',
            'video_url.url' => 'Enter a valid YouTube link.',
            'portal_host.required' => 'Hosted URL is required.',
            'portal_host.regex' => 'Enter a domain name such as example.com.',
        ];
    }
}
