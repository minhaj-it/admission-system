<?php

namespace App\Support;

use App\Models\AcademicSession;
use App\Models\AdmissionCategory;
use App\Models\DocumentType;
use App\Models\SchoolClass;
use App\Models\Shift;
use App\Models\StudyGroup;
use App\Models\Version;

/** The configurable lists shown on the Classes & Sessions page. */
final class MasterData
{
    public const TYPES = [
        'class' => ['model' => SchoolClass::class, 'label' => 'Class', 'icon' => 'bi-journal-bookmark'],
        'version' => ['model' => Version::class, 'label' => 'Version', 'icon' => 'bi-translate'],
        'session' => ['model' => AcademicSession::class, 'label' => 'Sessions/Year', 'icon' => 'bi-calendar3'],
        'shift' => ['model' => Shift::class, 'label' => 'Shift', 'icon' => 'bi-brightness-high'],
        'group' => ['model' => StudyGroup::class, 'label' => 'Group', 'icon' => 'bi-diagram-3'],
        'category' => ['model' => AdmissionCategory::class, 'label' => 'Category', 'icon' => 'bi-tags'],
        'document' => ['model' => DocumentType::class, 'label' => 'Document', 'icon' => 'bi-file-earmark-text'],
    ];

    public static function keys(): array
    {
        return array_keys(self::TYPES);
    }

    public static function config(string $type): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type];
    }
}
