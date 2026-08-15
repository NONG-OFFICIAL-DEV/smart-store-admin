<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SystemRoleLockedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\RoleService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    use ApiResponse;

    public function __construct(private RoleService $roles)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->roles->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'is_system',
        ]));

        return $this->success(
            RoleResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->roles->create($request->validated(), $request);

        return $this->created(new RoleResource($role), 'Role created successfully.');
    }

    public function show(Role $role): JsonResponse
    {
        return $this->success(new RoleResource($role->load('permissions')));
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        try {
            $role = $this->roles->update($role, $request->validated());
        } catch (SystemRoleLockedException $e) {
            return $this->error($e->getMessage(), 403, [], 'SYSTEM_ROLE_LOCKED');
        }

        return $this->success(new RoleResource($role), 'Role updated successfully.');
    }

    public function destroy(Role $role): JsonResponse
    {
        try {
            $this->roles->delete($role);
        } catch (SystemRoleLockedException $e) {
            return $this->error($e->getMessage(), 403, [], 'SYSTEM_ROLE_LOCKED');
        }

        return $this->noContent('Role deleted successfully.');
    }

    /**
     * POST /api/v1/roles/{role}/permissions/sync
     */
    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $request->validate([
            'permission_ids' => ['present', 'array'],
            'permission_ids.*' => ['uuid', 'exists:permissions,id'],
        ]);

        try {
            $role = $this->roles->syncPermissions($role, $request->permission_ids);
        } catch (SystemRoleLockedException $e) {
            return $this->error($e->getMessage(), 403, [], 'SYSTEM_ROLE_LOCKED');
        }

        return $this->success(new RoleResource($role), 'Permissions updated successfully.');
    }
}
