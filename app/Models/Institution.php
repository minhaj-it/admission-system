<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Institution extends Model
{
    public const DEFAULT_SETTINGS = [
        'image_max_kb' => 300,                       // photographs
        'file_max_kb' => 1024,                       // certificates and other documents
        'file_types' => ['pdf', 'jpg', 'png'],       // allowed formats for documents
    ];

    protected $fillable = ['name', 'short_name', 'address', 'phone', 'email', 'website', 'settings'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    /** Version 1 has exactly one institute (bound as a per-application singleton, see AppServiceProvider). */
    public static function current(): self
    {
        return app(self::class);
    }

    public static function forgetCurrent(): void
    {
        app()->forgetInstance(self::class);
    }

    public function setting(string $key): mixed
    {
        return $this->settings[$key] ?? self::DEFAULT_SETTINGS[$key] ?? null;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? route('branding.logo', ['v' => $this->updated_at?->timestamp]) : null;
    }
}
