<?php

namespace App\Http\Requests\Admin;

use App\Models\AdmissionCircular;
use Illuminate\Foundation\Http\FormRequest;

class DownloadsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = ['admit_instruction' => ['nullable', 'string', 'max:5000']];

        foreach (array_keys(AdmissionCircular::DOWNLOADS) as $key) {
            $rules[$key] = ['nullable', 'boolean'];
        }

        return $rules;
    }
}
