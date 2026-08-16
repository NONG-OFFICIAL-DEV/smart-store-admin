<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;

// app/Models/Feature.php
class Feature extends BaseModel
{
    protected $fillable = ['code', 'name', 'description', 'icon', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    private const CACHE_TTL = 3600;

    public static function allCodesCached(): array
    {
        return Cache::remember('features.all_codes', self::CACHE_TTL, fn () =>
            static::where('is_active', true)->pluck('code')->all()
        );
    }

    public function businessTypes()
    {
        return $this->belongsToMany(BusinessType::class, 'business_type_features')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function branchTypes()
    {
        return $this->belongsToMany(BranchType::class, 'branch_type_features')
            ->withPivot('is_required', 'is_default')
            ->withTimestamps();
    }

    /**
     * Every feature code available on a given branch type — the single
     * source of truth the runtime feature gate (BranchType::hasFeature(),
     * EnsureBranchHasFeature middleware) reads from. Cached the same way
     * Permission::allCodesCached() is: this changes rarely (branch_type_features
     * is admin-curated data, not per-request state) but is checked on
     * every gated request.
     */
    public static function codesForBranchType(string $branchTypeId): array
    {
        return Cache::remember(
            "branch_type_features.codes.{$branchTypeId}",
            self::CACHE_TTL,
            fn () => static::whereHas('branchTypes', fn ($q) => $q->where('branch_types.id', $branchTypeId))
                ->pluck('code')
                ->all()
        );
    }

    /**
     * The union of feature codes across every branch a tenant owns — what a
     * tenant Owner (not tied to any one branch) sees in nav-level gating.
     * Not cached: only computed once per me() call, not on every request.
     */
    public static function codesForTenant(string $tenantId): array
    {
        $branchTypeIds = Branch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('branch_type_id')
            ->distinct()
            ->pluck('branch_type_id');

        if ($branchTypeIds->isEmpty()) {
            return [];
        }

        return static::whereHas('branchTypes', fn ($q) => $q->whereIn('branch_types.id', $branchTypeIds))
            ->pluck('code')
            ->unique()
            ->values()
            ->all();
    }
}
