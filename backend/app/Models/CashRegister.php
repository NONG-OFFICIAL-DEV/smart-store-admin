<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

#[ScopedBy(TenantScope::class)]
class CashRegister extends BaseModel
{
    protected $fillable = [
        'branch_id', 'name', 'description', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashDrawers()
    {
        return $this->hasMany(CashDrawer::class);
    }

    public function hasOpenShift(): bool
    {
        return $this->cashDrawers()->whereNull('closed_at')->exists();
    }
}
