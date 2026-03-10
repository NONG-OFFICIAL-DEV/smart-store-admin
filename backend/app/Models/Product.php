<?php

namespace App\Models;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

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
        'product_type',
        'preparation_time',
        'calories',
        'is_available',
        'is_featured',
        'tax_category',
        'sort_order',
    ];

    protected $casts = [
        'base_price'       => 'decimal:2',
        'cost_price'       => 'decimal:2',
        'is_available'     => 'boolean',
        'is_featured'      => 'boolean',
        'sort_order'       => 'integer',
        'preparation_time' => 'integer',
        'calories'         => 'integer',
    ];

    // ─── Store ────────────────────────────────────────────────────────────────
    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'tenant_id',
                'category_id',
                'sku',
                'barcode',
                'name',
                'description',
                'base_price',
                'cost_price',
                'product_type',
                'preparation_time',
                'calories',
                'is_available',
                'is_featured',
                'tax_category',
                'sort_order',
            ])
            : $request;

        // ── Handle image upload ────────────────────────────────────────────────
        if ($request instanceof Request && $request->hasFile('image')) {
            // Delete old image if updating
            if ($id) {
                $old = static::find($id)?->getRawOriginal('image_url'); // raw path, bypass accessor
                if ($old) {
                    Storage::disk('public')->delete($old); // "products/xxx.jpg" direct
                }
            }

            $path = $request->file('image')->store('products', 'public');
            $data['image_url'] = $path; // ✅ stores "products/xxx.jpg" — same as qr_image_path
        }

        $result = parent::store($data, $id);

        // Sync modifier groups if provided
        if ($request instanceof Request && $request->has('modifier_group_ids')) {
            $product = $id ? static::find($id) : static::latest()->first();
            $product?->modifierGroups()->sync($request->modifier_group_ids);
        }

        return $result;
    }

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
}
