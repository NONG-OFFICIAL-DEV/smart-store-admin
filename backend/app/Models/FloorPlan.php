<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

// ─────────────────────────────────────────────────────────────────────────────
// FloorPlan
// ─────────────────────────────────────────────────────────────────────────────
#[ScopedBy(TenantScope::class)]
class FloorPlan extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'branch_id', 'name', 'sort_order', 'layout_json',
    ];

    protected $casts = [
        'layout_json' => 'array',
        'sort_order'  => 'integer',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function tables()
    {
        return $this->hasMany(Table::class);
    }
}




