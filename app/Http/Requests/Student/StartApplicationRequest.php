<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class StartApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the student routes already require a student account
    }

    /** Only ids are accepted; what they mean (open circular, allowed version ...) is decided by ApplicationService. */
    public function rules(): array
    {
        return [
            'circular_id' => ['required', 'integer', 'exists:admission_circulars,id'],
            'circular_offering_id' => ['required', 'integer', 'exists:circular_offerings,id'],
            'version_id' => ['required', 'integer', 'exists:versions,id'],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'study_group_id' => ['nullable', 'integer', 'exists:study_groups,id'],
            'admission_category_id' => ['required', 'integer', 'exists:admission_categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'circular_offering_id.required' => 'Select a class to apply.',
            'version_id.required' => 'Select a version.',
            'shift_id.required' => 'Select a shift.',
            'admission_category_id.required' => 'Select a category.',
        ];
    }
}
