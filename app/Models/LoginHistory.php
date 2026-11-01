<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Append-only security trail: login, failed, lockout, logout, password_reset. Never stores passwords. */
class LoginHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'event', 'identity', 'ip_address', 'user_agent'];

    public static function record(string $event, ?User $user, ?string $identity, Request $request): void
    {
        static::create([
            'user_id' => $user?->id,
            'event' => $event,
            'identity' => static::mask($identity),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);
    }

    /** Keep the first two characters only: enough to investigate, not enough to expose the value. */
    public static function mask(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return Str::substr($value, 0, 2) . str_repeat('*', max(0, min(Str::length($value), 12) - 2));
    }
}
