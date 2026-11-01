<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class SubmitApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('application'));
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
