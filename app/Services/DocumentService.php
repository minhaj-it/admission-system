<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\DocumentType;
use App\Support\ImageSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentService
{
    private const DISK = 'local'; // private disk: files are only reachable through the authorised download route

    public function store(Application $application, DocumentType $type, UploadedFile $file): ApplicationDocument
    {
        $field = 'doc_' . $type->id;
        $realPath = $file->getRealPath();
        $isPdf = $realPath && file_get_contents($realPath, false, null, 0, 5) === '%PDF-';

        if ($isPdf) {
            if ($type->kind === 'image') {
                throw ValidationException::withMessages([$field => 'Upload a photograph (JPG, PNG or WebP).']);
            }
            $bytes = (string) file_get_contents($realPath);
            $extension = 'pdf';
            $mime = 'application/pdf';
        } else {
            // images are decoded and re-encoded as PNG: metadata and any embedded payload are dropped
            $bytes = ImageSanitizer::toPng($file, $field, $type->kind === 'image' ? 800 : 1600);
            $extension = 'png';
            $mime = 'image/png';
        }

        $path = 'applications/' . $application->uuid . '/' . Str::random(40) . '.' . $extension;
        Storage::disk(self::DISK)->put($path, $bytes);

        $existing = $application->documents()->where('document_type_id', $type->id)->first();

        if ($existing) {
            Storage::disk(self::DISK)->delete($existing->path);
            $existing->fill(['path' => $path, 'mime' => $mime, 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes)]);
            // a replaced file has to be checked again
            $existing->verified_status = 'pending';
            $existing->verified_by = null;
            $existing->verified_at = null;
            $existing->remark = null;
            $existing->save();

            return $existing;
        }

        return $application->documents()->create([
            'document_type_id' => $type->id, 'path' => $path, 'mime' => $mime, 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes),
        ]);
    }

    public function remove(ApplicationDocument $document): void
    {
        Storage::disk(self::DISK)->delete($document->path);
        $document->delete();
    }
}
