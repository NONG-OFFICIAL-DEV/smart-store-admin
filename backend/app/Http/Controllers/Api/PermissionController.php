<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use App\Services\PermissionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    use ApiResponse;

    public function __construct(private PermissionService $permissions)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->permissions->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'group',
        ]));

        return $this->success(
            PermissionResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StorePermissionRequest $request): JsonResponse
    {
        $permission = $this->permissions->create($request->validated());

        return $this->created(new PermissionResource($permission), 'Permission created successfully.');
    }

    public function show(Permission $permission): JsonResponse
    {
        return $this->success(new PermissionResource($permission));
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): JsonResponse
    {
        $permission = $this->permissions->update($permission, $request->validated());

        return $this->success(new PermissionResource($permission), 'Permission updated successfully.');
    }

    public function destroy(Permission $permission): JsonResponse
    {
        $this->permissions->delete($permission);

        return $this->noContent('Permission deleted successfully.');
    }
}
