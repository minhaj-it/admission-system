<?php

namespace App\Services;

use App\Models\AdmissionCircular;
use App\Models\AuditLog;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CircularService
{
    private const FIELDS = ['title', 'description', 'opens_at', 'closes_at', 'circular_url', 'video_url', 'portal_host', 'unique_birth_id'];

    public function save(User $actor, ?AdmissionCircular $circular, array $data): AdmissionCircular
    {
        $active = ! empty($data['is_active']);
        $values = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'opens_at' => $data['opens_at'],
            'closes_at' => $data['closes_at'],
            'circular_url' => $data['circular_url'],
            'video_url' => ($data['video_url'] ?? null) ?: null,
            'portal_host' => $data['portal_host'],
            'unique_birth_id' => ! empty($data['unique_birth_id']),
        ];

        return DB::transaction(function () use ($actor, $circular, $data, $values, $active) {
            if (! $circular) {
                $circular = new AdmissionCircular($values + ['type' => $data['type']]);
                $circular->institution_id = Institution::current()->id;
                $circular->created_by = $actor->id;
                $circular->status = $active ? 'published' : 'draft';
                $circular->save();

                AuditLog::record('circular.created', $circular, null, ['title' => $circular->title, 'type' => $circular->type, 'status' => $circular->status]);

                return $circular;
            }

            $old = $this->snapshot($circular);
            $circular->fill($values);

            // The Active switch moves a circular between draft and published; an archived one stays archived.
            if ($circular->status !== 'archived') {
                $circular->status = $active ? 'published' : 'draft';
            }

            $circular->save();

            $new = $this->snapshot($circular);
            $changedOld = array_diff_assoc($old, $new);

            if ($changedOld) {
                AuditLog::record('circular.updated', $circular, $changedOld, array_diff_assoc($new, $old));
            }

            return $circular;
        });
    }

    public function archive(AdmissionCircular $circular): void
    {
        $this->setStatus($circular, 'archived', 'circular.archived');
    }

    /** A restored circular returns as a draft; it must be published again on purpose. */
    public function restore(AdmissionCircular $circular): void
    {
        abort_unless($circular->status === 'archived', 422);
        $this->setStatus($circular, 'draft', 'circular.restored');
    }

    public function delete(AdmissionCircular $circular): void
    {
        if ($circular->offerings()->exists()) {
            throw ValidationException::withMessages(['circular' => 'This circular already has class setups. Archive it instead of deleting it.']);
        }

        DB::transaction(function () use ($circular) {
            AuditLog::record('circular.deleted', $circular, ['title' => $circular->title], null);
            $circular->delete();
        });
    }

    public function saveDownloads(AdmissionCircular $circular, array $data): void
    {
        $downloads = [];
        foreach (array_keys(AdmissionCircular::DOWNLOADS) as $key) {
            $downloads[$key] = ! empty($data[$key]);
        }

        $old = ['downloads' => $circular->downloads, 'admit_instruction' => $circular->admit_instruction ? '(text)' : null];

        $circular->downloads = $downloads;
        $circular->admit_instruction = $downloads['admit_card'] ? ($data['admit_instruction'] ?? null) : null;
        $circular->save();

        AuditLog::record('circular.downloads_updated', $circular, $old, ['downloads' => $downloads, 'admit_instruction' => $circular->admit_instruction ? '(text)' : null]);
    }

    private function setStatus(AdmissionCircular $circular, string $status, string $action): void
    {
        $old = ['status' => $circular->status];
        $circular->status = $status;
        $circular->save();

        AuditLog::record($action, $circular, $old, ['status' => $status]);
    }

    private function snapshot(AdmissionCircular $circular): array
    {
        return [
            'title' => $circular->title,
            'description' => (string) $circular->description,
            'opens_at' => $circular->opens_at?->format('Y-m-d H:i'),
            'closes_at' => $circular->closes_at?->format('Y-m-d H:i'),
            'circular_url' => $circular->circular_url,
            'video_url' => (string) $circular->video_url,
            'portal_host' => (string) $circular->portal_host,
            'unique_birth_id' => $circular->unique_birth_id ? 'yes' : 'no',
            'status' => $circular->status,
        ];
    }
}
