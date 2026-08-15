<?php

namespace App\Repositories\Eloquent;

use App\Models\ModifierGroup;
use App\Repositories\Contracts\ModifierGroupRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class ModifierGroupRepository extends BaseRepository implements ModifierGroupRepositoryInterface
{
    protected array $searchable = ['name'];

    public function __construct(ModifierGroup $model)
    {
        parent::__construct($model);
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        // No timestamp columns at all — name is the only sensible default.
        $query->orderBy($sortBy ?: 'name', filter_var($sortDesc, FILTER_VALIDATE_BOOLEAN) ? 'desc' : 'asc');
    }
}
