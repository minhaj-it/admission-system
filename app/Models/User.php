<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const TYPE_STAFF = 'staff';
    public const TYPE_STUDENT = 'student';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    /*
     | account_type and status are deliberately NOT fillable: they can only be set
     | explicitly in server code, never from a request.
     */
    protected $fillable = ['name', 'username', 'email', 'mobile', 'password'];

    protected $hidden = ['password', 'remember_token'];

    /** Per-request cache of the role's permission keys (flipped for fast lookup). */
    protected ?array $permissionSet = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mobile_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isStaff(): bool
    {
        return $this->account_type === self::TYPE_STAFF;
    }

    public function isStudent(): bool
    {
        return $this->account_type === self::TYPE_STUDENT;
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasSuperAdminRole(): bool
    {
        return $this->role?->slug === Role::SUPER_ADMIN;
    }

    /** Active staff account holding the active, locked Super Admin role. */
    public function isSuperAdmin(): bool
    {
        return $this->isStaff() && $this->isActive() && $this->hasSuperAdminRole() && (bool) $this->role->is_active;
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->isStaff() || ! $this->isActive() || ! $this->role || ! $this->role->is_active) {
            return false;
        }

        if ($this->hasSuperAdminRole()) {
            return true;
        }

        $this->permissionSet ??= array_flip($this->role->permissionKeys());

        return isset($this->permissionSet[$permission]);
    }

    /**
     * May this user edit, deactivate or reset the password of $target?
     * Prevents privilege escalation: nobody manages an account that holds powers they lack.
     */
    public function canManage(User $target): bool
    {
        if (! $this->isStaff() || ! $target->isStaff()) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $target->role) {
            return true;
        }

        if ($target->role->is_locked || $target->hasSuperAdminRole()) {
            return false;
        }

        return collect($target->role->permissionKeys())->every(fn ($permission) => $this->hasPermission($permission));
    }

    public static function activeSuperAdminCount(): int
    {
        return static::query()
            ->where('account_type', self::TYPE_STAFF)
            ->where('status', self::STATUS_ACTIVE)
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SUPER_ADMIN)->where('is_active', true))
            ->count();
    }

    public function homeRoute(): string
    {
        return $this->isStaff() ? 'admin.dashboard' : 'student.home';
    }

    /** 01XXXXXXXXX, +8801XXXXXXXXX, 8801XXXXXXXXX -> 01XXXXXXXXX (other input is returned trimmed). */
    public static function normalizeMobile(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/[\s\-]+/', '', trim($value));

        if (str_starts_with($digits, '+88')) {
            return substr($digits, 3);
        }

        if (str_starts_with($digits, '88') && strlen($digits) === 13) {
            return substr($digits, 2);
        }

        return $digits;
    }

    /** Login identity may be an e-mail, a Bangladeshi mobile number or a username. */
    public static function findByIdentity(string $identity): ?self
    {
        $identity = trim($identity);

        if (str_contains($identity, '@')) {
            return static::where('email', Str::lower($identity))->first();
        }

        $mobile = static::normalizeMobile($identity);

        if (preg_match('/^01[3-9]\d{8}$/', (string) $mobile)) {
            return static::where('mobile', $mobile)->first();
        }

        return static::where('username', $identity)->first();
    }
}
