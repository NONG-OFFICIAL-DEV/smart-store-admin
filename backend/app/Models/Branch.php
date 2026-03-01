<?php

namespace App\Models;

use Illuminate\Http\Request;

class Branch extends BaseModel
{
    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'country',
        'postal_code',
        'latitude',
        'longitude',
        'phone',
        'email',
        'tax_rate',
        'service_charge_rate',
        'receipt_footer',
        'is_open',
        'is_active',
    ];

    protected $casts = [
        'tax_rate'             => 'decimal:4',
        'service_charge_rate'  => 'decimal:4',
        'latitude'             => 'decimal:6',
        'longitude'            => 'decimal:6',
        'is_open'              => 'boolean',
        'is_active'            => 'boolean',
    ];

    // ─── Store ────────────────────────────────────────────────────────────────
    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'tenant_id',
                'name',
                'type',
                'address_line1',
                'address_line2',
                'city',
                'state',
                'country',
                'postal_code',
                'latitude',
                'longitude',
                'phone',
                'email',
                'tax_rate',
                'service_charge_rate',
                'receipt_footer',
                'is_open',
                'is_active',
            ])
            : $request;

        return parent::store($data, $id);
    }

    // ─── Relationships ────────────────────────────────────────────────────────
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function hours()
    {
        return $this->hasMany(BranchHour::class);
    }

    public function staff()
    {
        return $this->hasMany(Staff::class);
    }

    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'branch_menus')
            ->withPivot('available_from', 'available_until', 'days_of_week', 'sort_order');
    }

    public function floorPlans()
    {
        return $this->hasMany(FloorPlan::class);
    }

    public function tables()
    {
        return $this->hasMany(Table::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function inventoryStock()
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function dailySalesSummaries()
    {
        return $this->hasMany(DailySalesSummary::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────
    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->country,
        ])->filter()->implode(', ');
    }
}
