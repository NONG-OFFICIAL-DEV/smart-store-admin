<?php

namespace App\Models;

use Illuminate\Http\Request;

class PurchaseOrderItem extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'purchase_order_id',
        'ingredient_id',
        'quantity_ordered',
        'quantity_received',
        'unit_price',
        'total_price',
        'received_at',
    ];

    protected $casts = [
        'quantity_ordered'  => 'decimal:3',
        'quantity_received' => 'decimal:3',
        'unit_price'        => 'decimal:4',
        'total_price'       => 'decimal:2',
        'received_at'       => 'datetime',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'purchase_order_id',
                'ingredient_id',
                'quantity_ordered',
                'unit_price',
            ])
            : $request;

        if (!empty($data['quantity_ordered']) && !empty($data['unit_price'])) {
            $data['total_price']       = $data['quantity_ordered'] * $data['unit_price'];
            $data['quantity_received'] = 0;
        }

        return parent::store($data, $id);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
