<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[ScopedBy(TenantScope::class)]
class MartPurchaseOrder extends Model
{
    protected $keyType      = 'string';
    public    $incrementing = false;
    protected $guarded      = [];

    protected static function booted(): void
    {
        static::creating(fn($m) => $m->id ??= (string) Str::uuid());
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    public function items()
    {
        return $this->hasMany(MartPurchaseOrderItem::class);
    }
    public function createdBy()
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }
}
