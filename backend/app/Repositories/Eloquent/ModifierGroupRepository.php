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

    // Every list consumer (the new Options tab) needs each group's choices
    // as chips and how many products use it, so both are eager-loaded here
    // rather than per-call — a group list is never large enough for this to
    // be a meaningful N+1 concern.
    public function query(): Builder
    {
        return parent::query()->with('options')->withCount('products');
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        // No timestamp columns at all — name is the only sensible default.
        $query->orderBy($sortBy ?: 'name', filter_var($sortDesc, FILTER_VALIDATE_BOOLEAN) ? 'desc' : 'asc');
    }
}
