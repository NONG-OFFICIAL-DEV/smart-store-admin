<?php

namespace App\Repositories\Eloquent;

use App\Models\Ingredient;
use App\Repositories\Contracts\IngredientRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class IngredientRepository extends BaseRepository implements IngredientRepositoryInterface
{
    protected array $searchable = ['name', 'unit'];

    public function __construct(Ingredient $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return $this->model->newQuery()->with('preferredSupplier');
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['low_stock'])) {
            $query->where('reorder_point', '>', 0)
                ->whereHas('stockRecords', function (Builder $q) {
                    $q->whereColumn('quantity_on_hand', '<=', 'ingredients.reorder_point');
                });
        }
    }
}
