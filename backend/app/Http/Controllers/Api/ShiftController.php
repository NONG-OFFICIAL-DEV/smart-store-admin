<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShiftRequest;
use App\Http\Requests\UpdateShiftRequest;
use App\Http\Resources\ShiftResource;
use App\Http\Resources\StaffShiftResource;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\StaffShift;
use App\Services\ShiftService;
use App\Services\StaffShiftService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShiftController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ShiftService $shifts,
        private StaffShiftService $assignments
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->shifts->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'is_active',
        ]));

        return $this->success(
            ShiftResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreShiftRequest $request): JsonResponse
    {
        $shift = $this->shifts->create($request->validated(), $request);

        return $this->created(new ShiftResource($shift), 'Shift created successfully.');
    }

    public function show(Shift $shift): JsonResponse
    {
        return $this->success(new ShiftResource($shift));
    }

    public function update(UpdateShiftRequest $request, Shift $shift): JsonResponse
    {
        $shift = $this->shifts->update($shift, $request->validated());

        return $this->success(new ShiftResource($shift), 'Shift updated successfully.');
    }

    public function destroy(Shift $shift): JsonResponse
    {
        $this->shifts->delete($shift);

        return $this->noContent('Shift deleted successfully.');
    }

    /**
     * GET /api/v1/staff/{staff}/shifts
     *
     * Routed here (not ShiftAssignmentController) because of how
     * routes/api.php groups it under the staff prefix — this and the two
     * clock actions below previously had no matching controller methods at
     * all (a guaranteed 500 if ever called; nothing in the frontend calls
     * them today — it only ever hits shift-assignments/{id}/clock-in|out).
     * Now that StaffShiftService exists (see ShiftAssignmentController),
     * delegates to it rather than duplicating clock-in/out guard clauses.
     */
    public function byStaff(Request $request, Staff $staff): JsonResponse
    {
        $paginator = $this->assignments->list([
            'staff_id' => $staff->id,
            'perPage' => min((int) $request->get('per_page', 20), 100),
        ]);

        return $this->success(StaffShiftResource::collection($paginator->items()), meta: [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function clockIn(Staff $staff): JsonResponse
    {
        $assignment = StaffShift::where('staff_id', $staff->id)
            ->whereDate('shift_date', today())
            ->first();

        if (! $assignment) {
            return $this->error('No shift scheduled for today.', 404, [], 'NO_SHIFT_TODAY');
        }

        try {
            $assignment = $this->assignments->clockIn($assignment);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'ALREADY_CLOCKED_IN');
        }

        return $this->success(new StaffShiftResource($assignment), 'Clocked in successfully.');
    }

    public function clockOut(Staff $staff): JsonResponse
    {
        $assignment = StaffShift::where('staff_id', $staff->id)
            ->whereDate('shift_date', today())
            ->first();

        if (! $assignment) {
            return $this->error('No shift scheduled for today.', 404, [], 'NO_SHIFT_TODAY');
        }

        try {
            $assignment = $this->assignments->clockOut($assignment);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'CLOCK_OUT_FAILED');
        }

        return $this->success(new StaffShiftResource($assignment), 'Clocked out successfully.');
    }
}
