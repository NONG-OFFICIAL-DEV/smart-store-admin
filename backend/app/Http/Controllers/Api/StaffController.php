<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Http\Resources\StaffResource;
use App\Models\Branch;
use App\Models\Staff;
use App\Services\StaffService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    use ApiResponse;

    public function __construct(private StaffService $staff)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->staff->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'tenant_id', 'branch_id', 'role_id', 'is_active',
        ]));

        return $this->paginated($paginator);
    }

    public function byBranch(Request $request, Branch $branch): JsonResponse
    {
        $paginator = $this->staff->byBranch($branch, $request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'role_id', 'is_active',
        ]));

        return $this->paginated($paginator);
    }

    public function store(StoreStaffRequest $request): JsonResponse
    {
        try {
            $staff = $this->staff->create($request->validated(), $request);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'VALIDATION_FAILED');
        }

        return $this->created(new StaffResource($staff), 'Staff member created successfully.');
    }

    public function show(Staff $staff): JsonResponse
    {
        return $this->success(new StaffResource($staff->load(['user', 'role', 'branch'])));
    }

    public function update(UpdateStaffRequest $request, Staff $staff): JsonResponse
    {
        try {
            $staff = $this->staff->update($staff, $request->validated());
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'VALIDATION_FAILED');
        }

        return $this->success(new StaffResource($staff), 'Staff updated successfully.');
    }

    public function destroy(Staff $staff): JsonResponse
    {
        $this->staff->deactivate($staff);

        return $this->success(null, 'Staff member deactivated successfully.', 200, [], 'STAFF_DEACTIVATED');
    }

    public function removePin(Staff $staff): JsonResponse
    {
        $this->staff->removePin($staff);

        return $this->success(null, 'PIN removed successfully.', 200, [], 'PIN_REMOVED');
    }

    public function resetPassword(Staff $staff): JsonResponse
    {
        try {
            $temporaryPassword = $this->staff->resetPassword($staff);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'VALIDATION_FAILED');
        }

        return $this->success(['temporary_password' => $temporaryPassword]);
    }

    private function paginated($paginator): JsonResponse
    {
        return $this->success(
            StaffResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }
}
