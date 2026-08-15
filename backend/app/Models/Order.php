<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

#[ScopedBy(TenantScope::class)]
class Order extends BaseModel
{
    protected $fillable = [
        'branch_id',
        'order_number',
        'order_type',
        'status',
        'table_id',
        'customer_id',
        'staff_id',
        'cashier_id',
        'delivery_address_id',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'service_charge_amount',
        'delivery_fee',
        'tips_amount',
        'total_amount',
        'notes',
        'coupon_code',
        'loyalty_points_earned',
        'loyalty_points_redeemed',
        'source',
        'estimated_ready_at',
        'completed_at',
        'customer_type',
        'queue_number'
    ];

    protected $casts = [
        'subtotal'               => 'decimal:2',
        'discount_amount'        => 'decimal:2',
        'tax_amount'             => 'decimal:2',
        'service_charge_amount'  => 'decimal:2',
        'delivery_fee'           => 'decimal:2',
        'tips_amount'            => 'decimal:2',
        'total_amount'           => 'decimal:2',
        'loyalty_points_earned'  => 'integer',
        'loyalty_points_redeemed' => 'integer',
        'estimated_ready_at'     => 'datetime',
        'completed_at'           => 'datetime',
    ];

    // ─── Store ────────────────────────────────────────────────────────────────
    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'branch_id',
                'order_type',
                'table_id',
                'customer_id',
                'staff_id',
                'delivery_address_id',
                'notes',
                'coupon_code',
                'source',
                'customer_type',
            ])
            : $request;

        if (!$id) {
            $data['order_number'] = static::generateOrderNumber($data['branch_id'] ?? '');
            $data['status']       = 'pending';
            $data['subtotal']     = 0;
            $data['total_amount'] = 0;
        }

        $result = parent::store($data, $id);

        // Add items if included in request
        if ($request instanceof Request && $request->has('items') && !$id) {
            $order = static::latest()->first();
            $order?->syncItems($request->items);
        }

        return $result;
    }

    // ─── Status transitions ───────────────────────────────────────────────────
    public static function updateStatus(string $id, string $newStatus, ?string $staffId = null, ?string $notes = null)
    {
        $order = static::find($id);
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $fromStatus = $order->status;
        $order->update([
            'status'       => $newStatus,
            'completed_at' => in_array($newStatus, ['completed', 'cancelled']) ? now() : null,
        ]);

        // Log the status change
        OrderStatusHistory::create([
            'order_id'            => $id,
            'from_status'         => $fromStatus,
            'to_status'           => $newStatus,
            'changed_by_staff_id' => $staffId,
            'notes'               => $notes,
        ]);

        // Free table if order is done
        if (in_array($newStatus, ['completed', 'cancelled']) && $order->table_id) {
            Table::updateStatus($order->table_id, Table::STATUS_AVAILABLE);
        }

        return response()->json(['success' => true, 'data' => $order->fresh()], 200);
    }

    // ─── Add / recalculate items ──────────────────────────────────────────────
    public function syncItems(array $items): void
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $product  = Product::find($item['product_id']);
            $variant  = isset($item['variant_id']) ? ProductVariant::find($item['variant_id']) : null;
            $quantity = $item['quantity'] ?? 1;

            $unitPrice   = $product->getPriceForBranch($this->branch_id);
            $unitPrice  += $variant?->price_adjustment ?? 0;
            $totalPrice  = $unitPrice * $quantity;

            $orderItem = OrderItem::create([
                'order_id'     => $this->id,
                'product_id'   => $item['product_id'],
                'variant_id'   => $item['variant_id'] ?? null,
                'product_name' => $product->name,
                'quantity'     => $quantity,
                'unit_price'   => $unitPrice,
                'total_price'  => $totalPrice,
                'notes'        => $item['notes'] ?? null,
            ]);

            // Add modifiers
            foreach ($item['modifiers'] ?? [] as $modifier) {
                $option = ModifierOption::find($modifier['modifier_option_id']);
                if ($option) {
                    OrderItemModifier::create([
                        'order_item_id'      => $orderItem->id,
                        'modifier_option_id' => $option->id,
                        'option_name'        => $option->name,
                        'price_adjustment'   => $option->price_adjustment,
                        'quantity'           => $modifier['quantity'] ?? 1,
                    ]);
                    $totalPrice += $option->price_adjustment * ($modifier['quantity'] ?? 1);
                }
            }

            $subtotal += $totalPrice;
        }

        // Recalculate totals
        $taxRate      = Branch::find($this->branch_id)?->tax_rate ?? 0;
        $taxAmount    = $subtotal * $taxRate;
        $totalAmount  = $subtotal + $taxAmount;

        $this->update([
            'subtotal'     => $subtotal,
            'tax_amount'   => $taxAmount,
            'total_amount' => $totalAmount,
        ]);
    }

    // ─── Relationships ────────────────────────────────────────────────────────
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function diningTable()
    {
        return $this->belongsTo(Table::class, 'table_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function cashier()
    {
        return $this->belongsTo(Staff::class, 'cashier_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('changed_at');
    }

    public function kitchenTickets()
    {
        return $this->hasMany(KitchenDisplayTicket::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────
    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber($order->branch_id);
            }
        });
    }

    public static function generateOrderNumber(string $branchId): string
    {
        // Get short branch tag (first 3 chars, uppercase, letters only)
        $branch = \App\Models\Branch::find($branchId);
        $tag    = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $branch?->name ?? 'BR'), 0, 3));

        do {
            $number = 'ORD-' . $tag . '-' . now()->format('ymd') . '-' . strtoupper(Str::random(4));
        } while (static::where('order_number', $number)->exists());

        return $number;
    }

    public function getAmountPaidAttribute(): float
    {
        return (float) $this->payments()->where('status', 'completed')->sum('amount');
    }

    public function getAmountDueAttribute(): float
    {
        return max(0, (float) $this->total_amount - $this->amount_paid);
    }
}
