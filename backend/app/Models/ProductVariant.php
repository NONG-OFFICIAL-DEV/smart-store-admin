<?php

namespace App\Models;

use Illuminate\Http\Request;

// ─────────────────────────────────────────────────────────────────────────────
// ProductVariant
// ─────────────────────────────────────────────────────────────────────────────
class ProductVariant extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'name',
        'price_adjustment',
        'sku_suffix',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'price_adjustment' => 'decimal:2',
        'is_default'       => 'boolean',
        'sort_order'       => 'integer',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'product_id',
                'name',
                'price_adjustment',
                'sku_suffix',
                'is_default',
                'sort_order',
            ])
            : $request;

        // Only one default variant per product
        if (!empty($data['is_default']) && $data['is_default']) {
            static::where('product_id', $data['product_id'])->update(['is_default' => false]);
        }

        return parent::store($data, $id);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getFinalPriceAttribute(): float
    {
        return (float) ($this->product->base_price + $this->price_adjustment);
    }
}
