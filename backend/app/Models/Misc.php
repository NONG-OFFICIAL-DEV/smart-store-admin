<?php

namespace App\Models;

use Illuminate\Http\Request;

// ══════════════════════════════════════════════════════════════════════════════
// FloorPlan
// ══════════════════════════════════════════════════════════════════════════════

class FloorPlan extends BaseModel
{
    protected $table      = 'floor_plans';
    public    $timestamps = false;

    protected $fillable = ['branch_id', 'name', 'sort_order', 'layout_json'];
    protected $casts    = ['layout_json' => 'array'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    public function tables()
    {
        return $this->hasMany(Table::class);
    }

    public static function store(Request $request, $id = null)
    {
        $data = $request->only(['branch_id', 'name', 'sort_order', 'layout_json']);
        if ($id) {
            $record = self::find($id);
            if (!$record) return response()->json(['error' => 'Floor plan not found'], 404);
            $record->update($data);
        } else {
            $record = self::create($data);
        }
        return response()->json(['success' => true, 'data' => $record->fresh()->load('tables')], $id ? 200 : 201);
    }
}


// ══════════════════════════════════════════════════════════════════════════════
// Table
// ══════════════════════════════════════════════════════════════════════════════

class Table extends BaseModel
{
    protected $table      = 'tables';
    public    $timestamps = false;

    protected $fillable = [
        'branch_id',
        'floor_plan_id',
        'table_number',
        'capacity',
        'shape',
        'position_x',
        'position_y',
        'qr_code',
        'status',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean', 'capacity' => 'integer'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    public function floorPlan()
    {
        return $this->belongsTo(FloorPlan::class);
    }
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function getActiveOrderAttribute(): ?Order
    {
        return $this->orders()
            ->whereNotIn('status', ['completed', 'cancelled', 'refunded'])
            ->latest()
            ->first();
    }

    public static function store(Request $request, $id = null)
    {
        $data = $request->only([
            'branch_id',
            'floor_plan_id',
            'table_number',
            'capacity',
            'shape',
            'position_x',
            'position_y',
            'qr_code',
            'status',
            'is_active',
        ]);
        if ($id) {
            $record = self::find($id);
            if (!$record) return response()->json(['error' => 'Table not found'], 404);
            $record->update($data);
        } else {
            $record = self::create($data);
        }
        return response()->json(['success' => true, 'data' => $record->fresh()], $id ? 200 : 201);
    }
}


// ══════════════════════════════════════════════════════════════════════════════
// Reservation
// ══════════════════════════════════════════════════════════════════════════════

class Reservation extends BaseModel
{
    protected $table = 'reservations';

    protected $fillable = [
        'branch_id',
        'table_id',
        'customer_id',
        'customer_name',
        'customer_phone',
        'party_size',
        'reserved_at',
        'duration_minutes',
        'status',
        'notes',
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
        'party_size'  => 'integer',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    public function table()
    {
        return $this->belongsTo(Table::class);
    }
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public static function store(Request $request, $id = null)
    {
        $data = $request->only([
            'branch_id',
            'table_id',
            'customer_id',
            'customer_name',
            'customer_phone',
            'party_size',
            'reserved_at',
            'duration_minutes',
            'status',
            'notes',
        ]);
        if ($id) {
            $record = self::find($id);
            if (!$record) return response()->json(['error' => 'Reservation not found'], 404);
            $record->update($data);
        } else {
            $data['status'] = 'pending';
            $record = self::create($data);
        }
        return response()->json([
            'success' => true,
            'data'    => $record->fresh()->load('table', 'customer'),
        ], $id ? 200 : 201);
    }
}


// ══════════════════════════════════════════════════════════════════════════════
// Promotion
// ══════════════════════════════════════════════════════════════════════════════

class Promotion extends BaseModel
{
    protected $table  = 'promotions';
    const UPDATED_AT  = null;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'type',
        'discount_value',
        'min_order_amount',
        'max_discount_amount',
        'applies_to',
        'applicable_ids',
        'start_at',
        'end_at',
        'usage_limit',
        'per_customer_limit',
        'is_active',
    ];

    protected $casts = [
        'applicable_ids'    => 'array',
        'discount_value'    => 'decimal:2',
        'min_order_amount'  => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'start_at'          => 'datetime',
        'end_at'            => 'datetime',
        'is_active'         => 'boolean',
        'usage_limit'       => 'integer',
        'usage_count'       => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    public function coupons()
    {
        return $this->hasMany(Coupon::class);
    }

    public function calculateDiscount(float $orderSubtotal): float
    {
        if ($this->min_order_amount && $orderSubtotal < $this->min_order_amount) return 0;

        $discount = match ($this->type) {
            'percentage'  => ($orderSubtotal * $this->discount_value) / 100,
            'fixed_amount' => $this->discount_value,
            default       => 0,
        };

        if ($this->max_discount_amount) {
            $discount = min($discount, $this->max_discount_amount);
        }

        return round($discount, 2);
    }

    public static function store(Request $request, $id = null)
    {
        $data = $request->only([
            'tenant_id',
            'name',
            'description',
            'type',
            'discount_value',
            'min_order_amount',
            'max_discount_amount',
            'applies_to',
            'applicable_ids',
            'start_at',
            'end_at',
            'usage_limit',
            'per_customer_limit',
            'is_active',
        ]);
        if ($id) {
            $record = self::find($id);
            if (!$record) return response()->json(['error' => 'Promotion not found'], 404);
            $record->update($data);
        } else {
            $record = self::create($data);
        }
        return response()->json(['success' => true, 'data' => $record->fresh()], $id ? 200 : 201);
    }
}


// ══════════════════════════════════════════════════════════════════════════════
// Coupon
// ══════════════════════════════════════════════════════════════════════════════

class Coupon extends BaseModel
{
    protected $table  = 'coupons';
    const UPDATED_AT  = null;

    protected $fillable = [
        'promotion_id',
        'code',
        'usage_limit',
        'is_active',
        'expires_at',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'expires_at' => 'datetime',
        'usage_count' => 'integer',
    ];

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }
    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) return false;
        return true;
    }

    public static function store(Request $request, $id = null)
    {
        $data = $request->only(['promotion_id', 'code', 'usage_limit', 'is_active', 'expires_at']);
        if ($id) {
            $record = self::find($id);
            if (!$record) return response()->json(['error' => 'Coupon not found'], 404);
            $record->update($data);
        } else {
            if (self::where('code', strtoupper($data['code']))->exists()) {
                return response()->json(['error' => 'Coupon code already exists'], 422);
            }
            $data['code'] = strtoupper($data['code']);
            $record = self::create($data);
        }
        return response()->json(['success' => true, 'data' => $record->fresh()], $id ? 200 : 201);
    }
}


// ══════════════════════════════════════════════════════════════════════════════
// CouponUsage
// ══════════════════════════════════════════════════════════════════════════════

class CouponUsage extends BaseModel
{
    protected $table      = 'coupon_usages';
    public    $timestamps = false;

    protected $fillable = ['coupon_id', 'order_id', 'customer_id', 'discount_applied'];
    protected $casts    = ['discount_applied' => 'decimal:2', 'used_at' => 'datetime'];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}


// ══════════════════════════════════════════════════════════════════════════════
// LoyaltyTransaction
// ══════════════════════════════════════════════════════════════════════════════

class LoyaltyTransaction extends BaseModel
{
    protected $table      = 'loyalty_transactions';
    public    $timestamps = false;

    protected $fillable = [
        'customer_id',
        'branch_id',
        'order_id',
        'type',
        'points',
        'balance_after',
        'description',
        'expires_at',
    ];

    protected $casts = [
        'points'       => 'integer',
        'balance_after' => 'integer',
        'expires_at'   => 'datetime',
        'created_at'   => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}


// ══════════════════════════════════════════════════════════════════════════════
// DailySalesSummary
// ══════════════════════════════════════════════════════════════════════════════

class DailySalesSummary extends BaseModel
{
    protected $table  = 'daily_sales_summary';
    const UPDATED_AT  = null;

    protected $fillable = [
        'branch_id',
        'date',
        'total_orders',
        'total_revenue',
        'total_discount',
        'total_tax',
        'total_tips',
        'net_revenue',
        'total_cogs',
        'gross_profit',
        'avg_order_value',
        'dine_in_orders',
        'takeaway_orders',
        'delivery_orders',
        'new_customers',
    ];

    protected $casts = [
        'date'            => 'date',
        'total_revenue'   => 'decimal:2',
        'net_revenue'     => 'decimal:2',
        'gross_profit'    => 'decimal:2',
        'avg_order_value' => 'decimal:2',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Rebuild the summary for a given branch + date from raw orders.
     */
    public static function rebuild(string $branchId, string $date): self
    {
        $orders = Order::where('branch_id', $branchId)
            ->whereDate('created_at', $date)
            ->where('status', 'completed')
            ->get();

        return self::updateOrCreate(
            ['branch_id' => $branchId, 'date' => $date],
            [
                'total_orders'    => $orders->count(),
                'total_revenue'   => $orders->sum('total_amount'),
                'total_discount'  => $orders->sum('discount_amount'),
                'total_tax'       => $orders->sum('tax_amount'),
                'total_tips'      => $orders->sum('tips_amount'),
                'net_revenue'     => $orders->sum('total_amount') - $orders->sum('discount_amount'),
                'avg_order_value' => $orders->count() > 0 ? $orders->avg('total_amount') : 0,
                'dine_in_orders'  => $orders->where('order_type', 'dine_in')->count(),
                'takeaway_orders' => $orders->where('order_type', 'takeaway')->count(),
                'delivery_orders' => $orders->where('order_type', 'delivery')->count(),
            ]
        );
    }
}


// ══════════════════════════════════════════════════════════════════════════════
// Notification
// ══════════════════════════════════════════════════════════════════════════════

class Notification extends BaseModel
{
    protected $table  = 'notifications';
    const UPDATED_AT  = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'role_id',
        'branch_id',
        'type',
        'title',
        'body',
        'data',
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function role()
    {
        return $this->belongsTo(Role::class);
    }
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }

    public static function store(Request $request, $id = null)
    {
        $data = $request->only(['tenant_id', 'user_id', 'role_id', 'branch_id', 'type', 'title', 'body', 'data']);
        if ($id) {
            $record = self::find($id);
            if (!$record) return response()->json(['error' => 'Notification not found'], 404);
            $record->update($data);
        } else {
            $record = self::create($data);
        }
        return response()->json(['success' => true, 'data' => $record->fresh()], $id ? 200 : 201);
    }
}


// ══════════════════════════════════════════════════════════════════════════════
// Allergen
// ══════════════════════════════════════════════════════════════════════════════

class Allergen extends BaseModel
{
    protected $table      = 'allergens';
    public    $timestamps = false;

    protected $fillable = ['name', 'icon_url'];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_allergens')->withPivot('is_may_contain');
    }

    public static function store(Request $request, $id = null)
    {
        $data = $request->only(['name', 'icon_url']);
        if ($id) {
            $record = self::find($id);
            if (!$record) return response()->json(['error' => 'Allergen not found'], 404);
            $record->update($data);
        } else {
            $record = self::create($data);
        }
        return response()->json(['success' => true, 'data' => $record->fresh()], $id ? 200 : 201);
    }
}


// ══════════════════════════════════════════════════════════════════════════════
// CustomerAddress
// ══════════════════════════════════════════════════════════════════════════════

class CustomerAddress extends BaseModel
{
    protected $table      = 'customer_addresses';
    public    $timestamps = false;

    protected $fillable = [
        'customer_id',
        'label',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'is_default',
    ];

    protected $casts = ['is_default' => 'boolean'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public static function store(Request $request, $id = null)
    {
        $data = $request->only([
            'customer_id',
            'label',
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'country',
            'latitude',
            'longitude',
            'is_default',
        ]);
        if ($id) {
            $record = self::find($id);
            if (!$record) return response()->json(['error' => 'Address not found'], 404);
            $record->update($data);
        } else {
            // If set as default, clear other defaults for this customer
            if (!empty($data['is_default'])) {
                self::where('customer_id', $data['customer_id'])->update(['is_default' => false]);
            }
            $record = self::create($data);
        }
        return response()->json(['success' => true, 'data' => $record->fresh()], $id ? 200 : 201);
    }
}


// ══════════════════════════════════════════════════════════════════════════════
// BranchProductOverride
// ══════════════════════════════════════════════════════════════════════════════

class BranchProductOverride extends BaseModel
{
    protected $table      = 'branch_product_overrides';
    public    $timestamps = false;

    protected $fillable = ['branch_id', 'product_id', 'override_price', 'is_available'];
    protected $casts    = ['override_price' => 'decimal:2', 'is_available' => 'boolean'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public static function store(Request $request, $id = null)
    {
        $data = $request->only(['branch_id', 'product_id', 'override_price', 'is_available']);
        $record = self::updateOrCreate(
            ['branch_id' => $data['branch_id'], 'product_id' => $data['product_id']],
            $data
        );
        return response()->json(['success' => true, 'data' => $record->fresh()], 201);
    }
}
