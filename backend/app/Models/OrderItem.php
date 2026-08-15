<?php

namespace App\Models;

use Illuminate\Http\Request;

// ─────────────────────────────────────────────────────────────────────────────
// OrderItem
// ─────────────────────────────────────────────────────────────────────────────
class OrderItem extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'order_id', 'product_id', 'variant_id', 'product_name',
        'quantity', 'unit_price', 'discount_amount', 'total_price',
        'status', 'notes', 'course',
        'unit_name',   
        'qty_per_base',
        'customer_type',   
        'is_lid_exchange', 
        'topup_amount',    
    ];

    protected $casts = [
        'quantity'        => 'integer',
        'unit_price'      => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_price'     => 'decimal:2',
        'qty_per_base'    => 'decimal:3',   
        'is_lid_exchange' => 'boolean',     
        'topup_amount'    => 'decimal:2',   
        'course'          => 'integer',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'order_id',
                'product_id',
                'variant_id',
                'product_name',
                'unit_name',        
                'qty_per_base',     
                'quantity',
                'unit_price',
                'discount_amount',
                'total_price',
                'status',
                'notes',
                'course',
                'customer_type',    
                'is_lid_exchange',  
                'topup_amount',     
        ])
        : $request;

        return parent::store($data, $id);
    }

    public static function updateStatus(string $id, string $status)
    {
        $item = static::find($id);
        if (!$item) return response()->json(['error' => 'Item not found'], 404);
        $item->update(['status' => $status]);
        return response()->json(['success' => true, 'data' => $item], 200);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function modifiers()
    {
        return $this->hasMany(OrderItemModifier::class);
    }
}
