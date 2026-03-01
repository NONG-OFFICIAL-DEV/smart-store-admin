<?php

namespace App\Models;

use Illuminate\Http\Request;

// ─────────────────────────────────────────────────────────────────────────────
// Promotion
// ─────────────────────────────────────────────────────────────────────────────
class Promotion extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'name', 'description', 'type',
        'discount_value', 'min_order_amount', 'max_discount_amount',
        'applies_to', 'applicable_ids', 'start_at', 'end_at',
        'usage_limit', 'per_customer_limit', 'is_active',
    ];

    protected $casts = [
        'discount_value'     => 'decimal:2',
        'min_order_amount'   => 'decimal:2',
        'max_discount_amount'=> 'decimal:2',
        'applicable_ids'     => 'array',
        'start_at'           => 'datetime',
        'end_at'             => 'datetime',
        'usage_limit'        => 'integer',
        'usage_count'        => 'integer',
        'per_customer_limit' => 'integer',
        'is_active'          => 'boolean',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'tenant_id', 'name', 'description', 'type',
                'discount_value', 'min_order_amount', 'max_discount_amount',
                'applies_to', 'applicable_ids', 'start_at', 'end_at',
                'usage_limit', 'per_customer_limit', 'is_active',
            ])
            : $request;

        return parent::store($data, $id);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function coupons()
    {
        return $this->hasMany(Coupon::class);
    }

    // ─── Calculate discount amount for an order ───────────────────────────────
    public function calculateDiscount(float $subtotal): float
    {
        if ($this->min_order_amount && $subtotal < $this->min_order_amount) return 0;

        $discount = match ($this->type) {
            'percentage'   => $subtotal * ($this->discount_value / 100),
            'fixed_amount' => $this->discount_value,
            default        => 0,
        };

        if ($this->max_discount_amount) {
            $discount = min($discount, $this->max_discount_amount);
        }

        return round($discount, 2);
    }

    public function isActive(): bool
    {
        if (!$this->is_active) return false;
        if ($this->start_at && now()->lt($this->start_at)) return false;
        if ($this->end_at && now()->gt($this->end_at)) return false;
        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) return false;
        return true;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Coupon
// ─────────────────────────────────────────────────────────────────────────────
class Coupon extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'promotion_id', 'code', 'usage_limit', 'is_active', 'expires_at',
    ];

    protected $casts = [
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'is_active'   => 'boolean',
        'expires_at'  => 'datetime',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only(['promotion_id', 'code', 'usage_limit', 'is_active', 'expires_at'])
            : $request;

        if (empty($data['code'])) {
            $data['code'] = strtoupper(\Illuminate\Support\Str::random(8));
        }

        return parent::store($data, $id);
    }

    // ─── Apply coupon to an order ─────────────────────────────────────────────
    public static function apply(string $code, string $orderId, ?string $customerId = null)
    {
        $coupon = static::where('code', $code)->where('is_active', true)->first();
        if (!$coupon) {
            return response()->json(['error' => 'Invalid coupon code'], 422);
        }
        if ($coupon->expires_at && now()->gt($coupon->expires_at)) {
            return response()->json(['error' => 'Coupon has expired'], 422);
        }
        if ($coupon->usage_limit && $coupon->usage_count >= $coupon->usage_limit) {
            return response()->json(['error' => 'Coupon usage limit reached'], 422);
        }

        $order    = Order::find($orderId);
        $discount = $coupon->promotion->calculateDiscount($order->subtotal);

        CouponUsage::create([
            'coupon_id'        => $coupon->id,
            'order_id'         => $orderId,
            'customer_id'      => $customerId,
            'discount_applied' => $discount,
        ]);

        $coupon->increment('usage_count');
        $coupon->promotion->increment('usage_count');

        $order->update([
            'coupon_code'     => $code,
            'discount_amount' => $discount,
            'total_amount'    => $order->subtotal - $discount + $order->tax_amount + $order->service_charge_amount,
        ]);

        return response()->json(['success' => true, 'discount' => $discount, 'data' => $order->fresh()], 200);
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// CouponUsage
// ─────────────────────────────────────────────────────────────────────────────
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


// ─────────────────────────────────────────────────────────────────────────────
// LoyaltyTransaction
// ─────────────────────────────────────────────────────────────────────────────
class LoyaltyTransaction extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'customer_id', 'branch_id', 'order_id',
        'type', 'points', 'balance_after', 'description', 'expires_at',
    ];

    protected $casts = [
        'points'       => 'integer',
        'balance_after'=> 'integer',
        'expires_at'   => 'datetime',
        'created_at'   => 'datetime',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'customer_id', 'branch_id', 'order_id',
                'type', 'points', 'balance_after', 'description', 'expires_at',
            ])
            : $request;

        $record = static::create($data);
        return response()->json(['success' => true, 'data' => $record], 201);
    }

    public function customer() { return $this->belongsTo(Customer::class); }
    public function branch()   { return $this->belongsTo(Branch::class); }
    public function order()    { return $this->belongsTo(Order::class); }
}


// ─────────────────────────────────────────────────────────────────────────────
// DailySalesSummary
// ─────────────────────────────────────────────────────────────────────────────
class DailySalesSummary extends BaseModel
{
    protected $table = 'daily_sales_summary';

    public $timestamps = false;

    protected $fillable = [
        'branch_id', 'date', 'total_orders', 'total_revenue',
        'total_discount', 'total_tax', 'total_tips', 'net_revenue',
        'total_cogs', 'gross_profit', 'avg_order_value',
        'dine_in_orders', 'takeaway_orders', 'delivery_orders', 'new_customers',
    ];

    protected $casts = [
        'date'            => 'date',
        'total_revenue'   => 'decimal:2',
        'net_revenue'     => 'decimal:2',
        'gross_profit'    => 'decimal:2',
        'avg_order_value' => 'decimal:2',
        'total_orders'    => 'integer',
        'new_customers'   => 'integer',
    ];

    // ─── Regenerate snapshot for a specific day ───────────────────────────────
    public static function generate(string $branchId, string $date)
    {
        $orders = Order::where('branch_id', $branchId)
            ->whereDate('created_at', $date)
            ->where('status', 'completed')
            ->get();

        $summary = [
            'branch_id'       => $branchId,
            'date'            => $date,
            'total_orders'    => $orders->count(),
            'total_revenue'   => $orders->sum('total_amount'),
            'total_discount'  => $orders->sum('discount_amount'),
            'total_tax'       => $orders->sum('tax_amount'),
            'total_tips'      => $orders->sum('tips_amount'),
            'net_revenue'     => $orders->sum('total_amount') - $orders->sum('discount_amount'),
            'dine_in_orders'  => $orders->where('order_type', 'dine_in')->count(),
            'takeaway_orders' => $orders->where('order_type', 'takeaway')->count(),
            'delivery_orders' => $orders->where('order_type', 'delivery')->count(),
            'avg_order_value' => $orders->count() ? round($orders->sum('total_amount') / $orders->count(), 2) : 0,
        ];

        static::updateOrCreate(
            ['branch_id' => $branchId, 'date' => $date],
            $summary
        );

        return response()->json(['success' => true, 'data' => $summary], 200);
    }

    public function store(array|Request $request, ?string $id = null)
    {
        // Daily summaries are generated, not manually stored
        return response()->json(['error' => 'Use DailySalesSummary::generate() instead'], 400);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// ActivityLog
// ─────────────────────────────────────────────────────────────────────────────
class ActivityLog extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'branch_id', 'user_id', 'action',
        'entity_type', 'entity_id', 'ip_address', 'user_agent', 'payload',
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'tenant_id', 'branch_id', 'user_id', 'action',
                'entity_type', 'entity_id', 'payload',
            ])
            : $request;

        $record = static::create($data);
        return response()->json(['success' => true, 'data' => $record], 201);
    }

    // ─── Log any action from anywhere ────────────────────────────────────────
    public static function log(
        string $tenantId,
        string $action,
        string $entityType,
        string $entityId,
        array $payload = [],
        ?string $userId = null,
        ?string $branchId = null
    ): void {
        static::create([
            'tenant_id'   => $tenantId,
            'branch_id'   => $branchId,
            'user_id'     => $userId,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
            'payload'     => $payload,
        ]);
    }

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function user()   { return $this->belongsTo(User::class); }
}


// ─────────────────────────────────────────────────────────────────────────────
// Notification
// ─────────────────────────────────────────────────────────────────────────────
class Notification extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'user_id', 'role_id', 'branch_id',
        'type', 'title', 'body', 'data', 'read_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'tenant_id', 'user_id', 'role_id', 'branch_id',
                'type', 'title', 'body', 'data',
            ])
            : $request;

        $record = static::create($data);
        return response()->json(['success' => true, 'data' => $record], 201);
    }

    public static function markRead(string $id)
    {
        $record = static::find($id);
        if (!$record) return response()->json(['error' => 'Not found'], 404);
        $record->update(['read_at' => now()]);
        return response()->json(['success' => true], 200);
    }

    public static function markAllRead(string $userId)
    {
        static::where('user_id', $userId)->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['success' => true], 200);
    }

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function user()   { return $this->belongsTo(User::class); }
    public function role()   { return $this->belongsTo(Role::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
}
