<?php

namespace App\Repositories\Eloquent;

use App\Models\StaffShift;
use App\Repositories\Contracts\StaffShiftRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * The old controller manually re-filtered by tenant (via a `whereHas('staff', ...)`
 * keyed off staff.tenant_id) on top of the global TenantScope, which already
 * applies to this model through `staff_shifts.branch_id` (no tenant_id column
 * here — see TenantScope's branch_id fallback). That's exactly the
 * double-filtering risk BaseRepository's docblock warns about, so it's
 * deliberately not repeated here.
 */
class StaffShiftRepository extends BaseRepository implements StaffShiftRepositoryInterface
{
    public function __construct(StaffShift $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return $this->model->newQuery()->with(['shift', 'staff.user', 'staff.role', 'branch']);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['shift_id'])) {
            $query->where('shift_id', $filters['shift_id']);
        }

        if (! empty($filters['staff_id'])) {
            $query->where('staff_id', $filters['staff_id']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('shift_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('shift_date', '<=', $filters['date_to']);
        }

        if (filter_var($filters['today'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereDate('shift_date', today());
        }
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        // Never had a user-choosable sort — always shift_date desc, then
        // created_at desc, matching the original controller exactly.
        $query->orderBy('shift_date', 'desc')->orderBy('created_at', 'desc');
    }
}
