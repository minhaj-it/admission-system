<?php

namespace App\Http\Requests\Admin;

use App\Support\MasterData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MasterItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        $type = (string) $this->route('type');
        $table = (new (MasterData::config($type)['model']))->getTable();

        $rules = [
            'name' => ['required', 'string', 'max:80', Rule::unique($table, 'name')->ignore($this->route('id'))],
        ];

        if ($type === 'class') {
            $rules['requires_group'] = ['nullable', 'boolean'];
        }

        if ($type === 'document') {
            $rules['kind'] = ['required', Rule::in(['image', 'file'])];
            $rules['step'] = ['required', Rule::in([1, 2, 4])];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'name.unique' => 'This name already exists.',
        ];
    }
}
