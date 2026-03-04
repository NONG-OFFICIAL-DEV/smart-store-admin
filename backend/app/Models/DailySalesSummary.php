<?php

namespace App\Models;

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
