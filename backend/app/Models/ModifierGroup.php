<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;
// ─────────────────────────────────────────────────────────────────────────────
// ModifierGroup
// ─────────────────────────────────────────────────────────────────────────────
#[ScopedBy(TenantScope::class)]
class ModifierGroup extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'name',
        'name_km',
        'selection_type',
        'min_selections',
        'max_selections',
        'is_required',
        'allow_custom_value',
        'custom_suffix',
    ];

    protected $casts = [
        'is_required'        => 'boolean',
        'min_selections'     => 'integer',
        'max_selections'     => 'integer',
        'allow_custom_value' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function options()
    {
        return $this->hasMany(ModifierOption::class, 'group_id')->orderBy('sort_order');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_modifier_groups')
            ->withPivot('sort_order');
    }
}
