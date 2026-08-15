<?php

namespace App\Models;

use Illuminate\Http\Request;

class OrderItemModifier extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'order_item_id', 'modifier_option_id',
        'option_name', 'price_adjustment', 'quantity',
    ];

    protected $casts = [
        'price_adjustment' => 'decimal:2',
        'quantity'         => 'integer',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'order_item_id', 'modifier_option_id',
                'option_name', 'price_adjustment', 'quantity',
            ])
            : $request;

        return parent::store($data, $id);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function modifierOption()
    {
        return $this->belongsTo(ModifierOption::class);
    }
}
