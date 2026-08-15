<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

// ─────────────────────────────────────────────────────────────────────────────
// ModifierOption
// ─────────────────────────────────────────────────────────────────────────────
// Was missing #[ScopedBy] entirely — not just an unhandled column shape.
// modifier_options has no tenant_id/branch_id of its own (tenant-owned only
// indirectly via group_id -> modifier_groups.tenant_id), and TenantScope
// now special-cases this table the same way it already does 'categories'.
// Options are also reachable by flat /options/{id} routes (shallow nested
// resource) with no group/tenant context in the URL, so this was a real
// cross-tenant read/write hole, not just a theoretical gap.
#[ScopedBy(TenantScope::class)]
class ModifierOption extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'group_id',
        'name',
        'price_adjustment',
        'is_available',
        'sort_order',
    ];

    protected $casts = [
        'price_adjustment' => 'decimal:2',
        'is_available'     => 'boolean',
        'sort_order'       => 'integer',
    ];

    public function group()
    {
        return $this->belongsTo(ModifierGroup::class, 'group_id');
    }
}
