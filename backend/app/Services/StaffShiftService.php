<?php

namespace App\Services;

use App\Models\StaffShift;
use App\Repositories\Contracts\StaffShiftRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class StaffShiftService extends BaseService
{
    public function __construct(StaffShiftRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function create(array $data): StaffShift
    {
        // The old duplicate check also matched on branch_id, but the actual
        // DB constraint (unique on shift_id+staff_id+shift_date — see the
        // staff_shifts migration) doesn't include it. A same shift+staff+date
        // assignment at a *different* branch passed the old check but then
        // hit the DB constraint anyway, surfacing as an uncaught
        // QueryException (a raw 500) instead of this clean validation error.
        // Matching the real constraint here also matches reality: the same
        // staff member can't work the same shift template on the same date
        // at two branches at once.
        $exists = StaffShift::where('shift_id', $data['shift_id'])
            ->where('staff_id', $data['staff_id'])
            ->where('shift_date', $data['shift_date'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'shift_id' => 'This staff member is already assigned to this shift on this date.',
            ]);
        }

        $assignment = $this->repository->create($data);

        return $assignment->load(['shift', 'staff.user', 'branch']);
    }

    public function update(StaffShift $assignment, array $data): StaffShift
    {
        $assignment = $this->repository->update($assignment, $data);

        return $assignment->load(['shift', 'staff.user', 'branch']);
    }

    public function delete(StaffShift $assignment): bool
    {
        return $this->repository->delete($assignment);
    }

    public function clockIn(StaffShift $assignment): StaffShift
    {
        if ($assignment->actual_start) {
            throw ValidationException::withMessages(['actual_start' => 'Already clocked in.']);
        }

        $assignment->update(['actual_start' => now()]);

        return $assignment->fresh()->load(['shift', 'staff.user', 'branch']);
    }

    public function clockOut(StaffShift $assignment): StaffShift
    {
        if (! $assignment->actual_start) {
            throw ValidationException::withMessages(['actual_start' => 'Must clock in first.']);
        }

        if ($assignment->actual_end) {
            throw ValidationException::withMessages(['actual_end' => 'Already clocked out.']);
        }

        $assignment->update(['actual_end' => now()]);

        return $assignment->fresh()->load(['shift', 'staff.user', 'branch']);
    }
}
