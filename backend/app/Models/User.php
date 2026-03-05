<?php

namespace App\Models;

use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Ramsey\Collection\Collection;
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
        'is_active',
        'last_login_at',
        'email_verified_at',
        'is_super_admin',
        'is_admin'
    ];

    // ── Hidden ────────────────────────────────────────────────────────────────
    protected $hidden = [
        'password_hash',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'is_active'         => 'boolean',
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

    // ── Store (create or update) ──────────────────────────────────────────────
    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'email',
                'phone',
                'first_name',
                'last_name',
                'avatar_url',
                'preferred_language',
                'is_active',
            ])
            : $request;

        // Hash password if provided
        if ($request instanceof Request && $request->filled('password')) {
            $data['password_hash'] = bcrypt($request->password);
        }

        if ($id) {
            $record = static::find($id);
            if (!$record) {
                return response()->json(['error' => 'User not found'], 404);
            }
            $record->update($data);
            return response()->json(['success' => true, 'data' => $record->fresh()], 200);
        }

        $record = static::create($data);
        return response()->json(['success' => true, 'data' => $record], 201);
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
        // $staff = $this->staff->first();
        // if ($staff) {
        //     return [
        //         'type'   => 'staff',
        //         'label'  => $staff->role?->name ?? 'Staff',
        //         'tenant' => $staff->tenant?->name,
        //         'role'   => $staff->role?->name,
        //     ];
        // }

        // User exists but not assigned anywhere yet
        return [
            'type'   => 'unassigned',
            'label'  => 'Unassigned',
            'tenant' => null,
            'role'   => null,
        ];
    }
}
