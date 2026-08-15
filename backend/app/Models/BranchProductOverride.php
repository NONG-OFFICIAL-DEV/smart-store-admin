<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

#[ScopedBy(TenantScope::class)]
class BranchProductOverride extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'branch_id',
        'product_id',
        'override_price',
        'is_available',
    ];

    protected $casts = [
        'override_price' => 'decimal:2',
        'is_available'   => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
