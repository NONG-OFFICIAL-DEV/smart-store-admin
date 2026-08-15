<?php

namespace App\Repositories\Eloquent;

use App\Models\MartPurchaseOrder;
use App\Repositories\Contracts\MartPurchaseOrderRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class MartPurchaseOrderRepository extends BaseRepository implements MartPurchaseOrderRepositoryInterface
{
    public function __construct(MartPurchaseOrder $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return parent::query()->with(['supplier:id,name', 'branch:id,name', 'items'])->withCount('items');
    }

    protected function applySearch(Builder $query, ?string $term): void
    {
        if (! $term) {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            $q->where('po_number', 'ilike', "%{$term}%")
                ->orWhereHas('supplier', fn($s) => $s->where('name', 'ilike', "%{$term}%"));
        });
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
    }
}
