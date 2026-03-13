<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MartPurchaseOrderItem extends Model
{
    protected $keyType      = 'string';
    public    $incrementing = false;
    public    $timestamps   = false;
    protected $guarded      = [];

    protected static function booted(): void
    {
        static::creating(fn($m) => $m->id ??= (string) Str::uuid());
    }

    public function martPurchaseOrder() { return $this->belongsTo(MartPurchaseOrder::class); }
    public function product()           { return $this->belongsTo(Product::class); }
    public function productUnit()       { return $this->belongsTo(ProductUnit::class); }
}