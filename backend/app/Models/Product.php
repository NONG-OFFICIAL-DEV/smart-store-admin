<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

use Illuminate\Database\Eloquent\Casts\Attribute;

#[ScopedBy(TenantScope::class)]
class Product extends BaseModel
{
    protected $fillable = [
        'tenant_id',
        'category_id',
        'sku',
        'barcode',
        'name',
        'description',
        'image_url',
        'base_price',
        'cost_price',
        'preparation_time',
        'calories',
        'is_available',
        'is_featured',
        'sort_order',
        // ── Mart fields ───────────────────────────────
        'selling_price',
        'wholesale_price',
        'stock_quantity',
        'reorder_level',
        'track_stock',
        'expiry_date',
        'unit',
        'supplier_code',
        // ── Food fields ───────────────────────────────
        'cup_sizes',
        'temperature_options',
        'shelf_life_hours',
    ];

    protected $attributes = [
        'is_available'  => true,
        'is_featured'   => false,
        'track_stock'   => false,
        'sort_order'    => 0,
        'base_price'    => 0.00,      // ← Default
        'cost_price'    => 0.00,      // ← Default
    ];
    // ── 2. Add to $casts ──────────────────────────────────────────────────────────
    protected $casts = [
        'base_price'       => 'decimal:2',
        'cost_price'       => 'decimal:2',
        'selling_price'    => 'decimal:2',
        'wholesale_price'  => 'decimal:2',
        'stock_quantity'   => 'float',
        'reorder_level'    => 'float',
        'is_available'     => 'boolean',
        'is_featured'      => 'boolean',
        'track_stock'      => 'boolean',
        'sort_order'       => 'integer',
        'preparation_time' => 'integer',
        'calories'         => 'integer',
        'expiry_date'      => 'date',
        'shelf_life_hours' => 'integer',
        'cup_sizes'        => 'array',
        'temperature_options' => 'array',
    ];

    protected function imageUrl(): Attribute
    {
        return Attribute::get(function ($value, $attributes) {
            return isset($attributes['image_url'])
                ? asset('storage/' . $attributes['image_url'])
                : null;
        });
    }
    // ─── Relationships ────────────────────────────────────────────────────────
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function defaultVariant()
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true);
    }

    public function modifierGroups()
    {
        return $this->belongsToMany(ModifierGroup::class, 'product_modifier_groups')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function recipe()
    {
        return $this->hasMany(ProductRecipe::class);
    }

    public function branchOverrides()
    {
        return $this->hasMany(BranchProductOverride::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────
    public function getPriceForBranch(string $branchId): float
    {
        $override = $this->branchOverrides()->where('branch_id', $branchId)->first();
        return (float) ($override?->override_price ?? $this->base_price);
    }

    public function getMarginAttribute(): ?float
    {
        if (!$this->cost_price || $this->base_price == 0) return null;
        return round((($this->base_price - $this->cost_price) / $this->base_price) * 100, 2);
    }

    // Add to app/Models/Product.php

    // ── Relationships ─────────────────────────────────────────────────────────────
    public function units()
    {
        return $this->hasMany(ProductUnit::class)->orderBy('sort_order');
    }

    public function activeUnits()
    {
        return $this->hasMany(ProductUnit::class)->where('is_active', true)->orderBy('sort_order');
    }

    public function baseUnit()
    {
        return $this->hasOne(ProductUnit::class)->where('is_base_unit', true);
    }

    // ── Find unit by barcode (for barcode scanner) ────────────────────────────────
    public static function findByBarcode(string $barcode): ?ProductUnit
    {
        return ProductUnit::where('barcode', $barcode)->with('product')->first();
    }
}
