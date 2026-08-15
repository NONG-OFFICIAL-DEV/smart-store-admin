<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    // ── UUID primary key (from BaseModel logic, applied directly here) ─────────
    public $incrementing = false;
    protected $keyType   = 'string';

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    // ── Fillable ──────────────────────────────────────────────────────────────
    protected $fillable = [
        'email',
        'phone',
        'password_hash',
        'first_name',
        'last_name',
        'avatar_url',
        'preferred_language',
        'telegram_chat_id',
        'notify_email',
        'notify_telegram',
        'notify_system',
        'is_active',
        'last_login_at',
        'email_verified_at',
        'is_super_admin',
        'is_admin',
        'must_change_password',
        'password_changed_at',
    ];

    // ── Hidden ────────────────────────────────────────────────────────────────
    protected $hidden = [
        'password_hash',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────
    protected $casts = [
        'email_verified_at'    => 'datetime',
        'last_login_at'        => 'datetime',
        'is_active'            => 'boolean',
        'notify_email'         => 'boolean',
        'notify_telegram'      => 'boolean',
        'notify_system'        => 'boolean',
        'must_change_password' => 'boolean',
        'password_changed_at'  => 'datetime',
    ];

    protected $appends = [
        'full_name',
    ];

    // ── Tell Laravel which column is the password ─────────────────────────────
    // Because our column is password_hash not password
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    // ── JWT required methods ──────────────────────────────────────────────────
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    // ── Relationships ─────────────────────────────────────────────────────────
    public function staff()
    {
        return $this->HasOne(Staff::class);
    }

    public function ownedTenant()
    {
        return $this->hasOne(Tenant::class, 'owner_user_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    // Check single permission
    public function hasPermission(string $code): bool
    {
        return $this->getAllPermissions()->contains($code);
    }

    // Get all permissions — cached 5 mins so DB isn't hit every request
    public function getAllPermissions(): \Illuminate\Support\Collection
    {
        return Cache::remember("user_perms_{$this->id}", 300, function () {
            return $this->staff()
                ->with('role.permissions')
                ->get()
                ->flatMap(fn($staff) => $staff->role?->permissions ?? [])
                ->pluck('code')
                ->unique()
                ->values();
        });
    }

    // Clear cache when role changes
    public function clearPermissionCache(): void
    {
        Cache::forget("user_perms_{$this->id}");
    }

    // Whether this user owns a tenant — cached 5 mins, checked on every
    // permission-gated request via CheckPermission, so a per-request DB hit
    // isn't worth it (ownership is set once at tenant creation and doesn't change).
    public function isTenantOwnerCached(): bool
    {
        return Cache::remember(
            "user_owns_tenant_{$this->id}",
            300,
            fn () => $this->ownedTenant()->exists()
        );
    }

    // User.php
    public function getResolvedTypeAttribute(): array
    {
        // Super Admin — highest priority
        if ($this->is_super_admin) {
            return [
                'type'   => 'super_admin',
                'label'  => 'Super Admin',
                'tenant' => null,
                'role'   => null,
            ];
        }

        // Tenant Owner
        if ($this->ownedTenant) {
            return [
                'type'   => 'owner',
                'label'  => 'Owner',
                'tenant' => $this->ownedTenant->name,
                'role'   => null,
            ];
        }

        // Staff — could have multiple staff records (multiple branches)
        $staff = $this->relationLoaded('staff') ? $this->staff : $this->staff;
        if ($staff) {
            return [
                'type'   => 'staff',
                'label'  => $staff->role?->name ?? 'Staff',
                'tenant' => $staff->tenant?->name,
                'role'   => $staff->role?->name,
            ];
        }

        // User exists but not assigned anywhere yet
        return [
            'type'   => 'unassigned',
            'label'  => 'Unassigned',
            'tenant' => null,
            'role'   => null,
        ];
    }

}
