<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

#[ScopedBy(TenantScope::class)]
class Category extends BaseModel
{
    public $timestamps = false;

    // menu_id was removed from here — CLAUDE.md documented it as a
    // fillable field + belongsTo relation referencing a column that was
    // never added to the categories table. Verified empirically: setting
    // it doesn't silently no-op as previously believed, it throws a real
    // Postgres "column does not exist" QueryException. category_tenant is
    // the actual, working tenant-linkage mechanism (see tenants() below).
    protected $fillable = [
        'parent_id',
        'name',
        'description',
        'image_url',
        'icon',
        'color',
        'sort_order',
        'is_active',
        'is_lid_exchange'
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'is_lid_exchange' => 'boolean',
        'sort_order' => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function products()
    {
        return $this->hasMany(Product::class)->orderBy('sort_order');
    }

    // ─── Relationships ────────────────────────────────────────────────────────
    public function tenants()
    {
        return $this->belongsToMany(Tenant::class, 'category_tenant');
    }
}
