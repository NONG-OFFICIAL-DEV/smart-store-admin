<?php

namespace App\Models;

use Illuminate\Http\Request;

class Menu extends BaseModel
{
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    // ─── Store ────────────────────────────────────────────────────────────────
    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only(['tenant_id', 'name', 'description', 'is_default', 'is_active'])
            : $request;

        // Only one default menu per tenant
        if (!empty($data['is_default']) && $data['is_default']) {
            static::where('tenant_id', $data['tenant_id'])->update(['is_default' => false]);
        }

        return parent::store($data, $id);
    }

    // ─── Relationships ────────────────────────────────────────────────────────
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'branch_menus')
            ->withPivot('available_from', 'available_until', 'days_of_week', 'sort_order');
    }

    public function categories()
    {
        return $this->hasMany(Category::class)->orderBy('sort_order');
    }
}
