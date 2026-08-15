<?php

namespace App\Repositories\Eloquent;

use App\Models\BusinessType;
use App\Repositories\Contracts\BusinessTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class BusinessTypeRepository extends BaseRepository implements BusinessTypeRepositoryInterface
{
    public function __construct(BusinessType $model)
    {
        parent::__construct($model);
    }

    // Small, fixed reference catalog (a handful of rows) — the frontend
    // consumes this as a flat list, not a paginated table, and the old
    // index() returned BusinessType::all() with no ordering at all despite
    // sort_order existing specifically for this.
    public function allOrdered(): Collection
    {
        return $this->query()->orderBy('sort_order')->get();
    }
}
