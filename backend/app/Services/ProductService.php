<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductService extends BaseService
{
    public function __construct(ProductRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function byCategory(string $categoryId, array $filters): LengthAwarePaginator
    {
        return $this->list(array_merge($filters, ['category_id' => $categoryId]));
    }

    public function detail(Product $product): Product
    {
        return $product->load([
            'category:id,name,icon,color',
            'variants' => fn($q) => $q->orderBy('sort_order')->orderBy('name'),
            'modifierGroups' => fn($q) => $q->orderBy('product_modifier_groups.sort_order')
                ->with(['options' => fn($q) => $q->orderBy('sort_order')->orderBy('name')]),
            'units' => fn($q) => $q->orderBy('is_base_unit', 'desc')->orderBy('qty_per_base'),
            'tenant' => fn($q) => $q->select('id', 'name', 'logo_url', 'currency', 'business_type_id')
                ->with('businessType:id,name,code,icon'),
            'branchOverrides' => fn($q) => $q->with('branch:id,name'),
        ]);
    }

    public function create(array $data, string $tenantId, ?UploadedFile $image = null): Product
    {
        return DB::transaction(function () use ($data, $tenantId, $image) {
            $product = $this->repository->create([
                'tenant_id' => $tenantId,
                ...$this->baseFields($data),
            ]);

            if ($image) {
                $product->update(['image_url' => $image->store("products/{$tenantId}", 'public')]);
            } elseif (! empty($data['image_url'])) {
                $product->update(['image_url' => $data['image_url']]);
            }

            if (! empty($data['variants'])) {
                $this->syncVariants($product, $data['variants']);
            }

            if (! empty($data['units'])) {
                $this->syncUnits($product, $data['units']);
            }

            return $product->load(['variants', 'units']);
        });
    }

    /**
     * Unifies what used to be two separate flows: ProductControllerV2's
     * full-replace edit-form update (name/category/variants/units/image,
     * all required) and the old ProductController's lenient partial update
     * (ProductManagement.vue's availability-toggle sends only
     * {is_available: bool}). UpdateProductRequest uses `sometimes` so a
     * partial payload validates fine, but that alone isn't enough — the
     * old V2 update() unconditionally did `$product->variants()->delete()`
     * before resyncing, which would have silently wiped every variant the
     * instant someone toggled availability from the list view. Guarded
     * behind array_key_exists so variants/units are only touched when the
     * client actually sent that key.
     */
    public function update(Product $product, array $data, ?string $tenantId, bool $isSuperAdmin, ?UploadedFile $image = null): Product
    {
        return DB::transaction(function () use ($product, $data, $tenantId, $isSuperAdmin, $image) {
            $fields = $this->baseFields($data);

            // Non-super-admins cannot move a product to a different tenant.
            if ($isSuperAdmin && $tenantId) {
                $fields['tenant_id'] = $tenantId;
            }

            $product->update($fields);

            if ($image) {
                if ($product->getRawOriginal('image_url') && Storage::disk('public')->exists($product->getRawOriginal('image_url'))) {
                    Storage::disk('public')->delete($product->getRawOriginal('image_url'));
                }
                $product->update(['image_url' => $image->store("products/{$product->tenant_id}", 'public')]);
            } elseif (array_key_exists('image_url', $data) && ! empty($data['image_url']) && ! str_starts_with($data['image_url'], 'http')) {
                $product->update(['image_url' => $data['image_url']]);
            }

            if (array_key_exists('variants', $data)) {
                $product->variants()->delete();
                if (! empty($data['variants'])) {
                    $this->syncVariants($product, $data['variants']);
                }
            }

            if (array_key_exists('units', $data)) {
                $product->units()->delete();
                if (! empty($data['units'])) {
                    $this->syncUnits($product, $data['units']);
                }
            }

            return $product->fresh(['variants', 'units']);
        });
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    public function attachModifierGroups(Product $product, array $modifierGroupIds): Product
    {
        // syncWithoutDetaching keeps existing links, only adds new ones.
        $product->modifierGroups()->syncWithoutDetaching($modifierGroupIds);

        return $product->load('modifierGroups.options');
    }

    public function findByBarcode(string $barcode): Product
    {
        $product = Product::where('barcode', $barcode)->where('is_available', true)->first();

        if (! $product) {
            throw ValidationException::withMessages(['barcode' => 'Product not found']);
        }

        if ($product->stock_quantity !== null && $product->stock_quantity < 1) {
            throw ValidationException::withMessages(['barcode' => 'Out of stock']);
        }

        return $product;
    }

    /**
     * Fields shared by create/update — everything except tenant_id (create
     * always sets it; update only moves it for a super admin) and the
     * image, which needs file-handling around it.
     */
    private function baseFields(array $data): array
    {
        $fields = [];
        foreach ([
            'category_id', 'name', 'description', 'sku', 'barcode', 'sort_order',
            'is_available', 'is_featured',
            'base_price', 'cost_price', 'preparation_time', 'calories',
            'cup_sizes', 'temperature_options', 'shelf_life_hours',
            'stock_quantity', 'reorder_level', 'track_stock', 'expiry_date', 'supplier_code',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[$field] = $data[$field];
            }
        }

        return $fields;
    }

    private function syncVariants(Product $product, array $variants): void
    {
        $hasDefault = collect($variants)->contains('is_default', true);

        foreach ($variants as $i => $v) {
            $product->variants()->create([
                'name' => $v['name'],
                'price_adjustment' => $v['price_adjustment'],
                'is_default' => $hasDefault ? ($v['is_default'] ?? false) : $i === 0,
                'sort_order' => $v['sort_order'] ?? $i,
            ]);
        }
    }

    private function syncUnits(Product $product, array $units): void
    {
        $hasBase = collect($units)->contains('is_base_unit', true);

        foreach ($units as $i => $u) {
            $isBase = $hasBase ? ($u['is_base_unit'] ?? false) : $i === 0;
            $product->units()->create([
                'unit_name' => $u['unit_name'],
                'qty_per_base' => $isBase ? 1 : ($u['qty_per_base'] ?? 1),
                'barcode' => ($u['barcode'] ?? null) ?: null,
                'retail_price' => $u['retail_price'],
                'wholesale_price' => $u['wholesale_price'] ?? null,
                'cost_price' => $u['cost_price'] ?? null,
                'is_base_unit' => $isBase,
                'is_active' => $u['is_active'] ?? true,
            ]);
        }
    }
}
