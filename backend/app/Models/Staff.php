<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

#[ScopedBy(TenantScope::class)]
class Staff extends BaseModel
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'user_id',
        'role_id',
        'employee_code',
        'pin_code',
        'hire_date',
        'hourly_rate',
        'is_active',
        'salary'
    ];

    protected $hidden = ['pin_code'];

    protected $casts = [
        'hire_date'    => 'date',
        'hourly_rate'  => 'decimal:2',
        'salary'  => 'decimal:2',
        'is_active'    => 'boolean',
    ];

    // ── Auto-generate employee code ───────────────────────────────────────────────
    // Format: EMP-{YEAR}-{4-digit sequence per tenant}
    // Example: EMP-2026-0001, EMP-2026-0002
    public static function generateEmployeeCode(?string $tenantId = null): string
    {
        $year   = now()->format('Y');
        $prefix = "EMP-{$year}-";

        // Count existing staff for this tenant this year
        $count = static::when(
            $tenantId,
            fn($q) => $q->where('tenant_id', $tenantId)
        )
            ->where('employee_code', 'like', $prefix . '%')
            ->count();

        $next = $count + 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    // ─── Relationships ────────────────────────────────────────────────────────
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function shifts()
    {
        return $this->hasMany(Shift::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────
    public function hasPermission(string $code): bool
    {
        return $this->role->hasPermission($code);
    }

    public function getFullNameAttribute(): string
    {
        return $this->user->full_name ?? '';
    }
}
