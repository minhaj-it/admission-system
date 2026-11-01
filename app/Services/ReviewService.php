<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationStatusHistory;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    public function changeStatus(User $actor, Application $application, string $to, ?string $remark): Application
    {
        return DB::transaction(function () use ($actor, $application, $to, $remark) {
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            $allowed = Application::TRANSITIONS[$locked->status] ?? [];
            if (! in_array($to, $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'This status change is not allowed.']);
            }

            if (in_array($to, [Application::REJECTED, Application::CORRECTION], true) && trim((string) $remark) === '') {
                throw ValidationException::withMessages(['remark' => 'A remark is required to reject an application or request a correction.']);
            }

            $from = $locked->status;
            $locked->status = $to;
            $locked->save();

            ApplicationStatusHistory::create([
                'application_id' => $locked->id, 'from_status' => $from, 'to_status' => $to, 'actor_id' => $actor->id, 'remark' => $remark ?: null,
            ]);

            AuditLog::record('application.status_changed', $locked, ['status' => $from], ['status' => $to, 'tracking_no' => $locked->tracking_no]);

            return $locked;
        });
    }

    public function verifyDocument(User $actor, ApplicationDocument $document, string $status, ?string $remark): void
    {
        if ($status === 'rejected' && trim((string) $remark) === '') {
            throw ValidationException::withMessages(['remark' => 'A remark is required to reject a document.']);
        }

        abort_unless($document->application->status !== Application::DRAFT, 404);

        $old = ['verified_status' => $document->verified_status];

        $document->verified_status = $status;
        $document->verified_by = $actor->id;
        $document->verified_at = now();
        $document->remark = $remark ?: null;
        $document->save();

        AuditLog::record('document.verified', $document->application, $old, [
            'document' => $document->type?->key, 'verified_status' => $status, 'remark' => $remark,
        ]);
    }
}
