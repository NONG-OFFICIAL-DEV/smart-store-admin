<?php

namespace App\Models;

use Illuminate\Http\Request;

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

    // ─── Store ────────────────────────────────────────────────────────────────
    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'tenant_id',
                'branch_id',
                'user_id',
                'role_id',
                'employee_code',
                'hire_date',
                'hourly_rate',
                'is_active',
                'salary'
            ])
            : $request;

        // ── Hash PIN if provided ──────────────────────────────────────────────────
        if ($request instanceof Request && $request->filled('pin_code')) {
            $data['pin_code'] = hash('sha256', $request->pin_code);
        }

        // ══════════════════════════════════════════════════════════════════════════
        // UPDATE
        // ══════════════════════════════════════════════════════════════════════════
        if ($id) {
            $record = static::find($id);
            if (!$record) {
                return response()->json(['error' => 'Staff not found'], 404);
            }
            $record->update($data);
            return response()->json([
                'success' => true,
                'data'    => $record->fresh()->load(['user', 'role', 'branch']),
            ], 200);
        }

        // ══════════════════════════════════════════════════════════════════════════
        // CREATE
        // ══════════════════════════════════════════════════════════════════════════

        // FIX: employee_code is empty string OR null — both should auto-generate
        if (empty($data['employee_code'])) {
            $data['employee_code'] = static::generateEmployeeCode(
                $data['tenant_id'] ?? null
            );
        }

        $record = static::create($data);

        return response()->json([
            'success' => true,
            'data'    => $record->load(['user', 'role', 'branch']),
        ], 201);
    }

    // ── Auto-generate employee code ───────────────────────────────────────────────
    // Format: EMP-{YEAR}-{4-digit sequence per tenant}
    // Example: EMP-2026-0001, EMP-2026-0002
    private static function generateEmployeeCode(?string $tenantId = null): string
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
