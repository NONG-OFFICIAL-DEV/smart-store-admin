<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductUnit extends Model
{
    protected $keyType  = 'string';
    public    $incrementing = false;

    protected $fillable = [
        'product_id',
        'unit_name',
        'unit_label',
        'qty_per_base',
        'barcode',
        'retail_price',
        'wholesale_price',
        'cost_price',
        'is_base_unit',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'qty_per_base'     => 'float',
        'retail_price'     => 'decimal:2',
        'wholesale_price'  => 'decimal:2',
        'cost_price'       => 'decimal:2',
        'is_base_unit'     => 'boolean',
        'is_active'        => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(fn($m) => $m->id = (string) Str::uuid());
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // ── Price based on customer type ──────────────────────────────────────────
    public function priceFor(string $customerType = 'retail'): float
    {
        return $customerType === 'wholesale'
            ? (float) ($this->wholesale_price ?? $this->retail_price)
            : (float) $this->retail_price;
    }
}
