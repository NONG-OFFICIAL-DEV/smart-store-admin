<?php

namespace App\Models;

use Illuminate\Http\Request;

class ProductRecipe extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'product_id', 'variant_id', 'ingredient_id',
        'quantity', 'unit', 'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'product_id', 'variant_id', 'ingredient_id',
                'quantity', 'unit', 'notes',
            ])
            : $request;

        return parent::store($data, $id);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
