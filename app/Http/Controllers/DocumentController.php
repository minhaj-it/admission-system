<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class DocumentController extends Controller
{
    /** The only way to open an uploaded document: authorised per request, never a public or guessable URL. */
    public function show(Application $application, ApplicationDocument $document): Response
    {
        Gate::authorize('view', $application);

        abort_unless($document->application_id === $application->id && Storage::disk('local')->exists($document->path), 404);

        if (auth()->user()->isStaff()) {
            AuditLog::record('document.viewed', $application, null, ['document' => $document->type?->key, 'tracking_no' => $application->tracking_no]);
        }

        return Storage::disk('local')->response($document->path, $document->downloadName(), [
            'Content-Type' => $document->mime,
            'Content-Disposition' => 'inline; filename="' . $document->downloadName() . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
