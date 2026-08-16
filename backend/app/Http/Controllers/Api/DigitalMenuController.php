<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Table;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class DigitalMenuController extends Controller
{
    /**
     * GET /api/v1/public/menu/{branchSlug}
     * GET /api/v1/public/menu/{branchSlug}/table/{tableId}
     */
    public function show(Request $request, string $branchSlug, ?string $tableId = null)
    {
        // ── 1. Branch ──────────────────────────────────────────────────────────
        $branch = Branch::with('tenant')
            ->where('slug', $branchSlug)
            ->where('is_active', true)
            ->first();

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found or inactive',
            ], 404);
        }

        // ── 2. Table (optional) ────────────────────────────────────────────────
        $table = null;
        if ($tableId) {
            $table = Table::where('id', $tableId)
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->first();

            if (!$table) {
                return response()->json([
                    'success' => false,
                    'message' => 'Table not found',
                ], 404);
            }
        }

        // ── 3. Active menus assigned to this branch ────────────────────────────
        $now       = now();
        $dayOfWeek = $now->dayOfWeek;       // 0=Sun … 6=Sat
        $timeNow   = $now->format('H:i:s');

        $branchMenus = $branch->branchMenus()
            ->with('menu')
            ->whereHas('menu', fn($q) => $q->where('is_active', true))
            ->where(
                fn($q) =>
                $q->whereNull('available_from')
                    ->orWhere(
                        fn($q2) =>
                        $q2->where('available_from', '<=', $timeNow)
                            ->where('available_until', '>=', $timeNow)
                    )
            )
            ->orderBy('sort_order')
            ->get()
            ->filter(
                fn($bm) =>
                !$bm->days_of_week ||
                    in_array($dayOfWeek, $bm->days_of_week)
            );

        if ($branchMenus->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No active menus available for this branch',
            ], 404);
        }

        // ── 4. Products for this tenant ────────────────────────────────────────
        // Categories belong to tenant (no menu_id column)
        // Products belong to tenant via category
        $products = Product::with([
            'category',
            'variants'                => fn($q) => $q->orderBy('sort_order'),
            'modifierGroups'          => fn($q) => $q->orderBy('sort_order'),
            'modifierGroups.options'  => fn($q) => $q->orderBy('sort_order'),
        ])
            ->where('tenant_id', $branch->tenant_id)
            ->where('is_available', true)
            ->orderBy('sort_order')
            ->get();

        if ($products->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No products available',
            ], 404);
        }

        // ── 5. Group products by category ──────────────────────────────────────
        $categoryIds = $products->pluck('category_id')->filter()->unique();

        $categories = Category::whereIn('id', $categoryIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn($category) => [
                'id'          => $category->id,
                'name'        => $category->name,
                'description' => $category->description,
                'image_url'   => $category->image_url,
                'icon'        => $category->icon,
                'color'       => $category->color,
                'sort_order'  => $category->sort_order,
                'products'    => $products
                    ->where('category_id', $category->id)
                    ->values()
                    ->map(fn($p) => $this->formatProduct($p)),
            ])
            ->filter(fn($cat) => count($cat['products']) > 0)
            ->values();

        // ── 6. Response ────────────────────────────────────────────────────────
        return response()->json([
            'success' => true,
            'data'    => [
                'branch'     => $this->formatBranch($branch),
                'table'      => $table ? $this->formatTable($table) : null,
                'menus'      => $branchMenus->map(fn($bm) => [
                    'id'             => $bm->menu->id,
                    'name'           => $bm->menu->name,
                    'description'    => $bm->menu->description,
                    'is_default'     => $bm->menu->is_default,
                    'available_from' => $bm->available_from,
                    'available_until' => $bm->available_until,
                ])->values(),
                'categories' => $categories,
            ],
        ]);
    }

    /**
     * GET /api/v1/public/menu/{branchSlug}/product/{productId}
     */
    public function product(string $branchSlug, string $productId)
    {
        $branch = Branch::where('slug', $branchSlug)
            ->where('is_active', true)
            ->firstOrFail();

        $product = Product::with([
            'category',
            'variants'               => fn($q) => $q->where('is_active', true)->orderBy('sort_order'),
            'modifierGroups'         => fn($q) => $q->orderBy('sort_order'),
            'modifierGroups.options' => fn($q) => $q->where('is_active', true)->orderBy('sort_order'),
        ])
            ->where('id', $productId)
            ->where('tenant_id', $branch->tenant_id)
            ->where('is_available', true)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => $this->formatProduct($product),
        ]);
    }

    // ── Formatters ─────────────────────────────────────────────────────────────

    private function formatBranch(Branch $branch): array
    {
        return [
            'id'                  => $branch->id,
            'name'                => $branch->name,
            'slug'                => $branch->slug,
            'address_line1'       => $branch->address_line1,
            'address_line2'       => $branch->address_line2,
            'city'                => $branch->city,
            'country'             => trim($branch->country),
            'phone'               => $branch->phone,
            'email'               => $branch->email,
            'is_open'             => $branch->is_open,
            'tax_rate'            => (float) $branch->tax_rate,
            'service_charge_rate' => (float) $branch->service_charge_rate,
            'receipt_footer'      => $branch->receipt_footer,
            // From tenant
            'currency'            => $branch->tenant->currency     ?? 'USD',
            'locale'              => $branch->tenant->locale       ?? 'en-US',
            'timezone'            => $branch->tenant->timezone     ?? 'UTC',
            'logo_url'            => $branch->tenant->logo_url,
            'primary_color'       => $branch->tenant->primary_color,
            'business_name'       => $branch->tenant->name,
        ];
    }

    private function formatTable(Table $table): array
    {
        return [
            'id'       => $table->id,
            'number'   => $table->table_number,
            'capacity' => $table->capacity,
            'shape'    => $table->shape,
            'status'   => $table->status,
        ];
    }

    private function formatProduct(Product $product): array
    {
        return [
            'id'               => $product->id,
            'name'             => $product->name,
            'description'      => $product->description,
            'image_url'        => $product->image_url,
            'base_price'       => (float) $product->base_price,
            'is_featured'      => $product->is_featured,
            'preparation_time' => $product->preparation_time,
            'calories'         => $product->calories,
            'sort_order'       => $product->sort_order,
            'category'         => $product->category ? [
                'id'   => $product->category->id,
                'name' => $product->category->name,
                'icon' => $product->category->icon,
            ] : null,
            'variants'         => $product->relationLoaded('variants')
                ? $product->variants->map(fn($v) => [
                    'id'               => $v->id,
                    'name'             => $v->name,
                    'price_adjustment' => (float) $v->price_adjustment,
                    'is_default'       => $v->is_default,
                    'sort_order'       => $v->sort_order,
                ])->values()
                : [],
            'modifier_groups'  => $product->relationLoaded('modifierGroups')
                ? $product->modifierGroups->map(fn($mg) => [
                    'id'             => $mg->id,
                    'name'           => $mg->name,
                    'selection_type' => $mg->selection_type,
                    'min_selections' => $mg->min_selections,
                    'max_selections' => $mg->max_selections,
                    'is_required'    => $mg->is_required,
                    'options'        => $mg->relationLoaded('options')
                        ? $mg->options->map(fn($o) => [
                            'id'         => $o->id,
                            'name'       => $o->name,
                            'price'      => (float) $o->price,
                            'is_default' => $o->is_default ?? false,
                        ])->values()
                        : [],
                ])->values()
                : [],
        ];
    }
}
