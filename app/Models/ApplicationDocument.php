<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationDocument extends Model
{
    // Only the service writes these; verification fields are never mass-assignable.
    protected $fillable = ['document_type_id', 'path', 'mime', 'size', 'sha256'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
    public function type(): BelongsTo { return $this->belongsTo(DocumentType::class, 'document_type_id'); }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function downloadName(): string
    {
        return ($this->type?->key ?? 'document') . '.' . (pathinfo($this->path, PATHINFO_EXTENSION) ?: 'bin');
    }
}
