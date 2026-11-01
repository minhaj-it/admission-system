<?php

namespace App\Http\Requests\Student;

use App\Models\User;
use App\Support\ApplicationData;
use App\Support\ApplicationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApplicationStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        // owner only, and only while the application can still be changed (checked before any validation runs)
        return $this->user()->can('update', $this->route('application'));
    }

    protected function prepareForValidation(): void
    {
        $clean = [];

        foreach (ApplicationData::stepKeys((int) $this->route('step')) as $key) {
            if (! $this->has($key)) {
                continue;
            }
            $value = $this->input($key);
            if (! is_string($value)) {
                continue;
            }
            $value = trim($value);
            if ($key === 'brn') {
                $value = preg_replace('/[\s\-]+/', '', $value);
            } elseif (str_ends_with($key, 'phone') || in_array($key, ['phone1', 'phone2'], true)) {
                $value = User::normalizeMobile($value);
            }
            $clean[$key] = $value;
        }

        $this->merge($clean);
    }

    public function rules(): array
    {
        $application = $this->route('application');
        $step = (int) $this->route('step');

        return ApplicationRules::forStep($step, $application)
            + ApplicationRules::documentRules($application, $step)
            + ['remove_docs' => ['nullable', 'array'], 'remove_docs.*' => ['integer']];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ((int) $this->route('step') !== 1) {
                return;
            }

            foreach (ApplicationRules::eligibilityErrors($this->route('application')->offering, $this->all()) as $field => $message) {
                if (! $validator->errors()->has($field)) {
                    $validator->errors()->add($field, $message);
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => "Student's name is required.",
            'name_bn.required' => "Student's name in Bangla is required.",
            'dob.required' => 'Date of birth is required.',
            'brn.required' => 'Birth registration number is required for this circular.',
            'brn.regex' => 'Enter the 10 to 20 digits of the birth registration number.',
            'phone1.required' => 'Emergency contact number is required.',
            'phone1.regex' => 'Enter an 11-digit mobile number starting with 01.',
            'phone2.regex' => 'Enter an 11-digit mobile number starting with 01.',
            '*.phone.regex' => 'Enter an 11-digit mobile number starting with 01.',
            'addr_present.required' => 'Present address is required.',
            'addr_permanent.required' => 'Permanent address is required.',
        ];
    }
}
