<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Institution;
use App\Support\ImageSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InstituteService
{
    private const DISK = 'local'; // private: the logo is served through /branding/logo, never by a public path

    public function update(array $data, ?UploadedFile $logo, bool $removeLogo): Institution
    {
        $institution = Institution::current();

        return DB::transaction(function () use ($institution, $data, $logo, $removeLogo) {
            $old = $institution->only(['name', 'short_name', 'address', 'phone', 'email', 'website']) + ['settings' => $institution->settings];

            $institution->fill([
                'name' => $data['name'],
                'short_name' => $data['short_name'] ?? null,
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'website' => $data['website'] ?? null,
                'settings' => [
                    'image_max_kb' => (int) $data['image_max_kb'],
                    'file_max_kb' => (int) $data['file_max_kb'],
                    'file_types' => array_values($data['file_types']),
                ],
            ]);

            $logoChanged = false;
            $previous = $institution->logo_path;

            if ($logo) {
                $png = ImageSanitizer::toPng($logo, 'logo');
                $name = 'branding/logo-' . Str::random(24) . '.png';
                Storage::disk(self::DISK)->put($name, $png);
                $institution->logo_path = $name;
                $logoChanged = true;
            } elseif ($removeLogo && $previous) {
                $institution->logo_path = null;
                $logoChanged = true;
            }

            $institution->save();

            if ($logoChanged && $previous) {
                Storage::disk(self::DISK)->delete($previous);
            }

            $new = $institution->only(['name', 'short_name', 'address', 'phone', 'email', 'website']) + ['settings' => $institution->settings];
            $changedOld = array_filter($old, fn ($v, $k) => $v !== $new[$k], ARRAY_FILTER_USE_BOTH);
            $changedNew = array_intersect_key($new, $changedOld);
            if ($logoChanged) {
                $changedNew['logo'] = $logo ? 'replaced' : 'removed';
            }

            if ($changedNew) {
                AuditLog::record('institute.updated', $institution, $changedOld, $changedNew);
            }

            Institution::forgetCurrent();

            return $institution;
        });
    }
}
