<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class SubmitApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The owner only. A repeated submit (double click, reload) must not fail: ApplicationService::submit
        // returns an already submitted application unchanged, so "can still be edited" is not checked here.
        return $this->user()->can('view', $this->route('application'));
    }

    public function rules(): array
    {
        return ['declaration' => ['accepted']];
    }

    public function messages(): array
    {
        return ['declaration.accepted' => 'Please accept the declaration before submitting.'];
    }
}
