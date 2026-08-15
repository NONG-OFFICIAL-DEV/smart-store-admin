<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;

class Permission extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'code', 'group', 'description',
    ];

    private const ALL_CODES_CACHE_KEY = 'permissions_all_codes';

    protected static function boot(): void
    {
        parent::boot();

        // Super-admin/owner /me responses return the full catalog on every
        // call — cache it and invalidate on any write, same pattern as
        // User::getAllPermissions()/clearPermissionCache().
        static::saved(fn () => Cache::forget(self::ALL_CODES_CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::ALL_CODES_CACHE_KEY));

        // Every tenant's Owner role must always hold 100% of the permission
        // catalog — attach new permissions automatically so nobody has to
        // remember to re-sync every tenant's Owner role by hand. Model-level
        // (not PermissionService::create()) so this holds regardless of
        // write path, including seeders/tinker.
        static::created(function (Permission $permission) {
            app(\App\Services\OwnerRoleProvisioner::class)->attachPermissionToAllOwnerRoles($permission);
        });
    }

    // All permission codes in the system — cached 5 mins.
    public static function allCodesCached(): array
    {
        return Cache::remember(self::ALL_CODES_CACHE_KEY, 300, fn () => static::pluck('code')->toArray());
    }

    // ─── Relationships ────────────────────────────────────────────────────────
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
