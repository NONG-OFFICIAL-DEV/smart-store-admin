<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

#[ScopedBy(TenantScope::class)]
class Role extends BaseModel
{
    public $timestamps = false;

    // Identifies protected system roles regardless of tenant renames/locale
    // (e.g. 'owner'). Only ever set internally — never client-writable.
    public const OWNER_CODE = 'owner';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'is_system',
        'code',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permissions',
            'role_id',
            'permission_id'
        );
    }

    public function staff()
    {
        return $this->hasMany(Staff::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────
    // Roles that may be picked in a normal role-assignment UI/API — excludes
    // the protected Owner role, which can only be granted via tenant creation
    // or ownership transfer.
    public function scopeAssignable($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('code')->orWhere('code', '!=', self::OWNER_CODE);
        });
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────
    public function hasPermission(string $code): bool
    {
        return $this->permissions->contains('code', $code);
    }

    public function isOwnerRole(): bool
    {
        return $this->code === self::OWNER_CODE;
    }

    // Every staff member holding this role has their permission set cached
    // (User::getAllPermissions(), 5 min TTL) — bust it immediately whenever
    // the role's permissions change, otherwise newly granted/revoked access
    // doesn't take effect until the cache naturally expires.
    public function clearStaffPermissionCache(): void
    {
        $this->staff()
            ->with('user')
            ->get()
            ->each(fn (Staff $staff) => $staff->user?->clearPermissionCache());
    }
}
