<?php

namespace App\Http\Requests\Admin;

use App\Models\CircularOffering;
use App\Models\SchoolClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OfferingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['roll_prefix' => strtoupper(trim((string) $this->input('roll_prefix')))]);
    }

    public function rules(): array
    {
        $active = fn (string $table) => Rule::exists($table, 'id')->where('is_active', true);
        $money = ['nullable', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2'];

        return [
            'programs' => ['required', 'array', 'min:1'],
            'programs.*' => ['integer', $active('school_classes')],
            'academic_session_id' => ['required', 'integer', $active('academic_sessions')],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['integer', $active('study_groups')],
            'shifts' => ['required', 'array', 'min:1'],
            'shifts.*' => ['integer', $active('shifts')],
            'versions' => ['required', 'array', 'min:1'],
            'versions.*' => ['integer', $active('versions')],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['integer', $active('admission_categories')],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['integer', $active('document_types')],
            'quotas' => ['nullable', 'array'],
            'quotas.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],

            'roll_scope' => ['required', Rule::in(array_keys(CircularOffering::ROLL_SCOPES))],
            'roll_prefix' => ['nullable', 'regex:/^[A-Z0-9]{1,10}$/'],
            'form_fee' => ['required', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2'],
            'admission_fee' => $money,
            'uniform_fee' => $money,
            'bank_charge' => $money,
            'gender_rule' => ['required', Rule::in(array_keys(CircularOffering::GENDERS))],
            'seats' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'limit_age' => ['nullable', 'boolean'],
            'age_on' => ['nullable', 'required_if:limit_age,1', 'date'],
            'min_age_months' => ['nullable', 'required_if:limit_age,1', 'integer', 'min:0', 'max:600'],
            'max_age_months' => ['nullable', 'required_if:limit_age,1', 'integer', 'min:0', 'max:600', 'gte:min_age_months'],
            'lottery_at' => ['nullable', 'date'],
            'viva_at' => ['nullable', 'date'],
            'written_at' => ['nullable', 'date'],
            'result_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $programs = array_map('intval', (array) $this->input('programs', []));

            if ($programs && ! $this->input('groups') && SchoolClass::whereIn('id', $programs)->where('requires_group', true)->exists()) {
                $validator->errors()->add('groups', 'Groups are required for classes that use groups (for example Nine and above).');
            }

            $categories = array_map('intval', (array) $this->input('categories', []));
            $quotas = array_filter((array) $this->input('quotas', []), fn ($q) => $q !== null && $q !== '');

            foreach (array_keys($quotas) as $categoryId) {
                if (! in_array((int) $categoryId, $categories, true)) {
                    $validator->errors()->add('quotas', 'A quota was given for a category that is not selected.');
                    break;
                }
            }

            $seats = $this->input('seats');
            if ($seats !== null && $seats !== '' && array_sum(array_map('intval', $quotas)) > (int) $seats) {
                $validator->errors()->add('seats', 'The category quotas add up to more than the available seats.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'programs.required' => 'Select at least one program.',
            'shifts.required' => 'Select at least one shift.',
            'versions.required' => 'Select at least one version.',
            'categories.required' => 'Select at least one category.',
            'academic_session_id.required' => 'Select a session.',
            'form_fee.required' => 'Form amount is required.',
            'age_on.required_if' => 'Age on date is required.',
            'min_age_months.required_if' => 'Minimum age month is required.',
            'max_age_months.required_if' => 'Maximum age month is required.',
            'max_age_months.gte' => 'Maximum age must not be below the minimum age.',
        ];
    }
}
