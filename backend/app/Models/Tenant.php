<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Request;

class Tenant extends BaseModel
{
    protected $fillable = [
        'name',
        'bu_type',
        'slug',
        'plan',
        'plan_expires_at',
        'owner_user_id',
        'logo_url',
        'primary_color',
        'timezone',
        'currency',
        'locale',
        'is_active',
        'business_type_id',
    ];

    protected $casts = [
        'plan_expires_at' => 'datetime',
        'trial_used_at'   => 'datetime',
        'is_active'       => 'boolean',
        'bu_type' => 'string',
    ];

    // ─── Store ────────────────────────────────────────────────────────────────
    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'name',
                'bu_type',
                'slug',
                'plan',
                'plan_expires_at',
                'logo_url',
                'primary_color',
                'timezone',
                'currency',
                'locale',
                'is_active',
                'business_type_id',
            ])
            : $request;

        return parent::store($data, $id);
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::get(function ($value) {
            return $value ? asset($value) : null;
        });
    }
    // ─── Relationships ────────────────────────────────────────────────────────
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_tenant');
    }

    public function staff()
    {
        return $this->hasMany(Staff::class);
    }

    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function menus()
    {
        return $this->hasMany(Menu::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function promotions()
    {
        return $this->hasMany(Promotion::class);
    }

    public function suppliers()
    {
        return $this->hasMany(Supplier::class);
    }

    public function businessType()
    {
        return $this->belongsTo(BusinessType::class, 'business_type_id');
    }

    public function hqBranch()
    {
        return $this->hasOne(Branch::class)
            ->whereHas('branchType', fn($q) => $q->where('is_hq', true));
    }

    // Get all features available to this tenant via business type
    public function availableFeatures()
    {
        return $this->businessType->features()->where('is_active', true)->get();
    }

    // Tenant.php — skip latestOfMany completely
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(TenantSubscription::class)
            ->whereIn('status', ['active', 'trial'])
            ->latest('created_at');  // just orders, no MAX(id) involved
    }

    public function subscriptions()
    {
        return $this->hasMany(TenantSubscription::class)->latest('created_at');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function subscriptionPlanHistory()
    {
        return $this->hasMany(
            SubscriptionPlanHistory::class
        );
    }

    public function plan()
    {
        return $this->hasOneThrough(
            Plan::class,
            TenantSubscription::class,
            'tenant_id', // FK on tenant_subscriptions
            'id',        // FK on plans
            'id',        // local key on tenants
            'plan_id'    // local key on tenant_subscriptions
        )->whereIn('tenant_subscriptions.status', ['active', 'trial']);
    }
}
