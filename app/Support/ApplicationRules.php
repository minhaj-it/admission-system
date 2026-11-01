<?php

namespace App\Support;

use App\Models\Application;
use App\Models\CircularOffering;
use App\Models\Institution;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

/** The single source of validation rules for the application form (used on every step save and again on submit). */
final class ApplicationRules
{
    public static function forStep(int $step, Application $application): array
    {
        $phone = 'regex:' . FormOptions::PHONE_REGEX;

        return match ($step) {
            1 => [
                'name' => ['required', 'string', 'max:120'],
                'name_bn' => ['required', 'string', 'max:120'],
                'dob' => ['required', 'date_format:Y-m-d', 'before:today', 'after:1980-01-01'],
                'brn' => [$application->circular->unique_birth_id ? 'required' : 'nullable', 'regex:/^\d{10,20}$/'],
                'phone1' => ['required', $phone],
                'phone2' => ['nullable', $phone],
                'blood' => ['nullable', Rule::in(FormOptions::BLOOD)],
                'nation' => ['nullable', 'string', 'max:40'],
                'religion' => ['required', Rule::in(FormOptions::RELIGION)],
                'gender' => ['required', Rule::in(FormOptions::GENDER)],
            ],
            2 => self::guardianRules($phone),
            3 => [
                'addr_present' => ['required', 'string', 'max:300'],
                'addr_permanent' => ['required', 'string', 'max:300'],
                'addr_guardian' => ['nullable', 'string', 'max:300'],
            ],
            4 => [
                'prev_inst' => ['nullable', 'string', 'max:120'],
                'prev_class' => ['nullable', 'string', 'max:120'],
                'prev_result' => ['nullable', 'string', 'max:60'],
                'prev_roll' => ['nullable', 'string', 'max:30'],
                'prev_version' => ['nullable', 'string', 'max:40'],
            ],
            default => [],
        };
    }

    private static function guardianRules(string $phone): array
    {
        $rules = [
            'guardian_type' => ['required', Rule::in(FormOptions::GUARDIAN_TYPE)],
            'guardian_rel' => ['required', 'string', 'max:40'],
        ];

        foreach (ApplicationData::ROLES as $role) {
            $rules["{$role}_name"] = ['required', 'string', 'max:120'];
            $rules["{$role}_phone"] = ['required', $phone];
            $rules["{$role}_edu"] = ['nullable', 'string', 'max:120'];
            $rules["{$role}_nid"] = ['nullable', 'regex:/^[A-Za-z0-9\-]{5,25}$/'];
            $rules["{$role}_occ"] = ['nullable', Rule::in(FormOptions::OCCUPATION)];
            $rules["{$role}_desig"] = ['nullable', 'string', 'max:120'];
            $rules["{$role}_office"] = ['nullable', 'string', 'max:255'];
            $rules["{$role}_income"] = ['nullable', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'];
        }

        return $rules;
    }

    /** Upload rules for the boxes shown on a step; the limits come from the Institute Profile. */
    public static function documentRules(Application $application, int $step): array
    {
        $institute = Institution::current();
        $fileTypes = array_map(fn ($t) => $t === 'jpg' ? 'jpg,jpeg' : $t, (array) $institute->setting('file_types'));
        $rules = [];

        foreach (self::documentTypes($application, $step) as $type) {
            $rules['doc_' . $type->id] = $type->kind === 'image'
                ? ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:' . (int) $institute->setting('image_max_kb')]
                : ['nullable', 'file', 'mimes:' . implode(',', $fileTypes), 'max:' . (int) $institute->setting('file_max_kb')];
        }

        return $rules;
    }

    /** @return \Illuminate\Support\Collection<int,\App\Models\DocumentType> the offering's documents shown on a step */
    public static function documentTypes(Application $application, int $step)
    {
        return $application->offering->documents()->where('document_types.step', $step)->orderBy('document_types.sort_order')->get();
    }

    /**
     * Rules that need the offering (gender and age limits).
     *
     * @return array<string,string> field => message
     */
    public static function eligibilityErrors(CircularOffering $offering, array $data): array
    {
        $errors = [];

        $gender = $data['gender'] ?? null;
        if ($gender && ! self::genderAllowed($offering, $gender)) {
            $errors['gender'] = 'This class is open for ' . strtolower($offering->gender_rule) . ' applicants only.';
        }

        if ($offering->limit_age && ! empty($data['dob'])) {
            $dob = Carbon::createFromFormat('Y-m-d', $data['dob'])->startOfDay();
            $months = AgeCalculator::months($dob, $offering->age_on->copy()->startOfDay());

            if ($months === null || $months < $offering->min_age_months || $months > $offering->max_age_months) {
                $errors['dob'] = 'You are not eligible to apply: the age on ' . $offering->age_on->format('d M Y')
                    . ' must be between ' . $offering->min_age_months . ' and ' . $offering->max_age_months . ' months.';
            }
        }

        return $errors;
    }

    public static function genderAllowed(CircularOffering $offering, string $gender): bool
    {
        return match ($offering->gender_rule) {
            'male' => $gender === 'Male',
            'female' => $gender === 'Female',
            default => true,
        };
    }
}
