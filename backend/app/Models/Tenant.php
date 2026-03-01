<?php

namespace App\Models;

use Illuminate\Http\Request;

class Tenant extends BaseModel
{
    protected $fillable = [
        'name',
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
    ];

    protected $casts = [
        'plan_expires_at' => 'datetime',
        'is_active'       => 'boolean',
    ];

    // ─── Store ────────────────────────────────────────────────────────────────
    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'name',
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
            ])
            : $request;

        return parent::store($data, $id);
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
}
