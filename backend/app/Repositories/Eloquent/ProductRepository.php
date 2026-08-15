<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    // 'price' isn't a real column (base_price is) — the old whitelist let
    // it through anyway, which would have thrown the moment anything
    // actually sent sort_by=price. Kept as an alias since nothing in the
    // frontend currently sends it (only `name` is sortable in the UI).
    private const SORT_ALIASES = ['price' => 'base_price'];
    private const SORTABLE = ['name', 'created_at', 'base_price', 'is_available'];

    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return parent::query()->with(['category:id,name']);
    }

    protected function applySearch(Builder $query, ?string $term): void
    {
        if (! $term) {
            return;
        }

        // Binary-safe, case-insensitive on all environments.
        $query->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($term).'%']);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['categories'])) {
            $categoryIds = is_array($filters['categories'])
                ? $filters['categories']
                : explode(',', $filters['categories']);

            $query->whereIn('category_id', array_filter($categoryIds));
        }

        if (array_key_exists('is_available', $filters) && $filters['is_available'] !== null && $filters['is_available'] !== '') {
            $query->where('is_available', filter_var($filters['is_available'], FILTER_VALIDATE_BOOLEAN));
        }
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        $sortBy = self::SORT_ALIASES[$sortBy] ?? $sortBy;
        $sortBy = in_array($sortBy, self::SORTABLE, true) ? $sortBy : 'name';
        $direction = filter_var($sortDesc, FILTER_VALIDATE_BOOLEAN) ? 'desc' : 'asc';
        $query->orderBy($sortBy, $direction);
    }
}
