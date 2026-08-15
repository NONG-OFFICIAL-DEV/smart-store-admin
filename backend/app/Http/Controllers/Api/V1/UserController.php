<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(private UserService $users)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->users->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'is_active', 'verified',
        ]));

        return $this->paginated($paginator);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->validated());

        return $this->created(new UserResource($user), 'User created successfully.');
    }

    public function show(User $user): JsonResponse
    {
        return $this->success(new UserResource($user->load(['ownedTenant', 'staff.role', 'staff.branch'])));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        try {
            $user = $this->users->update($user, $request->validated());
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'VALIDATION_FAILED');
        }

        return $this->success(new UserResource($user), 'User updated successfully.');
    }

    public function destroy(User $user): JsonResponse
    {
        try {
            $this->users->delete($user);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'VALIDATION_FAILED');
        }

        return $this->noContent('User deleted successfully.');
    }

    public function resetPassword(User $user): JsonResponse
    {
        $temporaryPassword = $this->users->resetPassword($user);

        return $this->success(['temporary_password' => $temporaryPassword], 'Password reset successfully.');
    }

    private function paginated($paginator): JsonResponse
    {
        return $this->success(
            UserResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }
}
