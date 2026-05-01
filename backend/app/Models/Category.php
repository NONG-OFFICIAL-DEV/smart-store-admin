<?php

namespace App\Models;

use Illuminate\Http\Request;

class Category extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'menu_id',
        'parent_id',
        'name',
        'description',
        'image_url',
        'icon',
        'color',
        'sort_order',
        'is_active',
        'is_lid_exchange'
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'is_lid_exchange' => 'boolean',
        'sort_order' => 'integer',
    ];

    // ─── Store ────────────────────────────────────────────────────────────────
    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'menu_id',
                'parent_id',
                'name',
                'description',
                'image_url',
                'icon',
                'color',
                'sort_order',
                'is_active',
                'is_lid_exchange'
            ])
            : $request;

        return parent::store($data, $id);
    }

    // ─── Relationships ────────────────────────────────────────────────────────
    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function products()
    {
        return $this->hasMany(Product::class)->orderBy('sort_order');
    }

    // ─── Relationships ────────────────────────────────────────────────────────
    public function tenants()
    {
        return $this->belongsToMany(Tenant::class, 'category_tenant');
    }
}
