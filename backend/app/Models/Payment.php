<?php

namespace App\Models;

use Illuminate\Http\Request;

// ─────────────────────────────────────────────────────────────────────────────
// Payment
// ─────────────────────────────────────────────────────────────────────────────
class Payment extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'order_id', 'branch_id', 'staff_id', 'payment_method',
        'amount', 'change_given', 'currency', 'exchange_rate',
        'status', 'gateway', 'gateway_transaction_id',
        'gateway_response', 'receipt_number', 'paid_at',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'change_given'     => 'decimal:2',
        'exchange_rate'    => 'decimal:6',
        'gateway_response' => 'array',
        'paid_at'          => 'datetime',
    ];

    // ─── Store ────────────────────────────────────────────────────────────────
    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'order_id', 'branch_id', 'staff_id', 'payment_method',
                'amount', 'change_given', 'currency', 'exchange_rate',
                'gateway', 'gateway_transaction_id',
            ])
            : $request;

        $data['status']  = 'completed';
        $data['paid_at'] = now();

        $payment = static::create($data);

        // Mark order as completed if fully paid
        $order = Order::find($data['order_id']);
        if ($order && $order->amount_due <= 0) {
            Order::updateStatus($order->id, 'completed', $data['staff_id'] ?? null);
        }

        return response()->json(['success' => true, 'data' => $payment], 201);
    }

    // ─── Relationships ────────────────────────────────────────────────────────
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Refund
// ─────────────────────────────────────────────────────────────────────────────
class Refund extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'payment_id', 'order_id', 'staff_id',
        'amount', 'reason', 'method', 'status', 'gateway_refund_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'payment_id', 'order_id', 'staff_id',
                'amount', 'reason', 'method',
            ])
            : $request;

        $data['status'] = 'completed';

        // Mark original payment as refunded
        $payment = Payment::find($data['payment_id']);
        $payment?->update(['status' => 'refunded']);

        // Update order status
        Order::updateStatus($data['order_id'], 'refunded', $data['staff_id']);

        $refund = static::create($data);
        return response()->json(['success' => true, 'data' => $refund], 201);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// CashDrawer
// ─────────────────────────────────────────────────────────────────────────────
class CashDrawer extends BaseModel
{
    protected $table = 'cash_drawers';

    public $timestamps = false;

    protected $fillable = [
        'branch_id', 'staff_id', 'opening_float',
        'expected_cash', 'actual_cash', 'variance', 'notes',
    ];

    protected $casts = [
        'opening_float' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'actual_cash'   => 'decimal:2',
        'variance'      => 'decimal:2',
        'opened_at'     => 'datetime',
        'closed_at'     => 'datetime',
    ];

    // ─── Open drawer session ──────────────────────────────────────────────────
    public static function open(string $branchId, string $staffId, float $openingFloat)
    {
        $session = static::create([
            'branch_id'     => $branchId,
            'staff_id'      => $staffId,
            'opening_float' => $openingFloat,
        ]);

        return response()->json(['success' => true, 'data' => $session], 201);
    }

    // ─── Close drawer session ─────────────────────────────────────────────────
    public static function close(string $id, float $actualCash, ?string $notes = null)
    {
        $drawer = static::find($id);
        if (!$drawer) return response()->json(['error' => 'Session not found'], 404);

        // Calculate expected cash: opening float + all cash payments
        $cashPayments = Payment::where('branch_id', $drawer->branch_id)
            ->where('payment_method', 'cash')
            ->where('status', 'completed')
            ->where('paid_at', '>=', $drawer->opened_at)
            ->sum('amount');

        $expectedCash = $drawer->opening_float + $cashPayments;
        $variance     = $actualCash - $expectedCash;

        $drawer->update([
            'expected_cash' => $expectedCash,
            'actual_cash'   => $actualCash,
            'variance'      => $variance,
            'closed_at'     => now(),
            'notes'         => $notes,
        ]);

        return response()->json(['success' => true, 'data' => $drawer], 200);
    }

    // ─── Relationships ────────────────────────────────────────────────────────
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
