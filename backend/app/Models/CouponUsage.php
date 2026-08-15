<?php

namespace App\Models;

use Illuminate\Http\Request;

class CouponUsage extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'coupon_id', 'order_id', 'customer_id', 'discount_applied',
    ];

    protected $casts = [
        'discount_applied' => 'decimal:2',
        'used_at'          => 'datetime',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only(['coupon_id', 'order_id', 'customer_id', 'discount_applied'])
            : $request;

        $record = static::create($data);
        return response()->json(['success' => true, 'data' => $record], 201);
    }

    public function coupon()   { return $this->belongsTo(Coupon::class); }
    public function order()    { return $this->belongsTo(Order::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
}
