<?php

namespace App\Repositories\Eloquent;

use App\Models\Plan;
use App\Repositories\Contracts\PlanRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class PlanRepository extends BaseRepository implements PlanRepositoryInterface
{
    public function __construct(Plan $model)
    {
        parent::__construct($model);
    }

    // Small, fixed catalog (a handful of plans) — consumed as a flat list,
    // not a paginated table.
    public function allOrdered(bool $activeOnly = false): Collection
    {
        return $this->query()
            ->with(['billingCycles', 'features'])
            ->when($activeOnly, fn($q) => $q->where('is_active', true))
            ->orderBy('price_usd')
            ->get();
    }
}
