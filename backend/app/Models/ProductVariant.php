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


// ─────────────────────────────────────────────────────────────────────────────
// BranchProductOverride
// ─────────────────────────────────────────────────────────────────────────────
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

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only(['branch_id', 'product_id', 'override_price', 'is_available'])
            : $request;

        // Upsert by branch + product
        $override = static::updateOrCreate(
            ['branch_id' => $data['branch_id'], 'product_id' => $data['product_id']],
            $data
        );

        return response()->json(['success' => true, 'data' => $override], 200);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
