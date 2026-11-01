<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CircularOffering extends Model
{
    public const ROLL_SCOPES = ['class_version' => 'Class Version', 'class' => 'Class', 'class_version_group' => 'Class Version Group'];
    public const GENDERS = ['both' => 'Both Male & Female', 'male' => 'Male', 'female' => 'Female'];

    protected $fillable = [
        'roll_scope', 'roll_prefix', 'form_fee', 'admission_fee', 'uniform_fee', 'bank_charge', 'gender_rule', 'seats',
        'limit_age', 'age_on', 'min_age_months', 'max_age_months', 'lottery_at', 'viva_at', 'written_at', 'result_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'form_fee' => 'decimal:2', 'admission_fee' => 'decimal:2', 'uniform_fee' => 'decimal:2', 'bank_charge' => 'decimal:2',
            'limit_age' => 'boolean', 'is_active' => 'boolean', 'age_on' => 'date',
            'lottery_at' => 'datetime', 'viva_at' => 'datetime', 'written_at' => 'datetime', 'result_at' => 'datetime',
        ];
    }

    public function circular(): BelongsTo
    {
        return $this->belongsTo(AdmissionCircular::class, 'circular_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function versions(): BelongsToMany
    {
        return $this->belongsToMany(Version::class, 'offering_versions', 'offering_id', 'version_id');
    }

    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(Shift::class, 'offering_shifts', 'offering_id', 'shift_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(StudyGroup::class, 'offering_groups', 'offering_id', 'study_group_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(AdmissionCategory::class, 'offering_categories', 'offering_id', 'admission_category_id')->withPivot('quota_seats');
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(DocumentType::class, 'offering_documents', 'offering_id', 'document_type_id')->withPivot('is_required');
    }

    /**
     * Fee lines that are filled in (amount above zero). A blank or zero line is simply not charged,
     * so a school circular can leave Admission/Uniform fee empty and a college circular can fill them.
     *
     * @return array<string,int> label => amount in paisa
     */
    public function feeLines(): array
    {
        $lines = [];

        foreach (['form_fee' => 'Application form fee', 'admission_fee' => 'Admission fee', 'uniform_fee' => 'Uniform fee', 'bank_charge' => 'Bank / gateway charge'] as $column => $label) {
            $minor = Money::toMinor($this->{$column});
            if ($minor > 0) {
                $lines[$label] = $minor;
            }
        }

        return $lines;
    }

    /** Amount payable with the application, in paisa. Always computed on the server. */
    public function payableMinor(): int
    {
        return array_sum($this->feeLines());
    }
}
