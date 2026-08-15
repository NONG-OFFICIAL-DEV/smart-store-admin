<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffShiftRequest;
use App\Http\Requests\UpdateStaffShiftRequest;
use App\Http\Resources\StaffShiftResource;
use App\Models\StaffShift;
use App\Services\StaffShiftService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShiftAssignmentController extends Controller
{
    use ApiResponse;

    public function __construct(private StaffShiftService $assignments)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->assignments->list($request->only([
            'shift_id', 'staff_id', 'branch_id', 'date_from', 'date_to', 'today', 'perPage',
        ]));

        return $this->success(
            StaffShiftResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreStaffShiftRequest $request): JsonResponse
    {
        try {
            $assignment = $this->assignments->create($request->validated());
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'DUPLICATE_ASSIGNMENT');
        }

        return $this->created(new StaffShiftResource($assignment), 'Staff assigned to shift successfully.');
    }

    public function show(StaffShift $shift_assignment): JsonResponse
    {
        return $this->success(new StaffShiftResource($shift_assignment->load(['shift', 'staff.user', 'branch'])));
    }

    public function update(UpdateStaffShiftRequest $request, StaffShift $shift_assignment): JsonResponse
    {
        $assignment = $this->assignments->update($shift_assignment, $request->validated());

        return $this->success(new StaffShiftResource($assignment), 'Assignment updated successfully.');
    }

    public function destroy(StaffShift $shift_assignment): JsonResponse
    {
        $this->assignments->delete($shift_assignment);

        return $this->noContent('Assignment removed successfully.');
    }

    public function clockIn(StaffShift $shift_assignment): JsonResponse
    {
        try {
            $assignment = $this->assignments->clockIn($shift_assignment);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'ALREADY_CLOCKED_IN');
        }

        return $this->success(new StaffShiftResource($assignment), 'Clocked in successfully.');
    }

    public function clockOut(StaffShift $shift_assignment): JsonResponse
    {
        try {
            $assignment = $this->assignments->clockOut($shift_assignment);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'CLOCK_OUT_FAILED');
        }

        return $this->success(new StaffShiftResource($assignment), 'Clocked out successfully.');
    }
}
