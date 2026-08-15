<?php

namespace App\Models;

use Illuminate\Http\Request;

// ─────────────────────────────────────────────────────────────────────────────
// OrderStatusHistory
// ─────────────────────────────────────────────────────────────────────────────
class OrderStatusHistory extends BaseModel
{
    public $timestamps = false;
    protected $table = 'order_status_history';

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'changed_by_staff_id',
        'notes',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only(['order_id', 'from_status', 'to_status', 'changed_by_staff_id', 'notes'])
            : $request;

        return parent::store($data, $id);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'changed_by_staff_id');
    }
}
