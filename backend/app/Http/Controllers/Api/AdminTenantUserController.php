<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdminTenantUserService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AdminTenantUserController extends Controller
{
    use ApiResponse;

    public function __construct(private AdminTenantUserService $users)
    {
    }

    public function index(Tenant $tenant): JsonResponse
    {
        return $this->success($this->users->usersFor($tenant));
    }

    public function deactivate(Tenant $tenant, User $user): JsonResponse
    {
        try {
            $row = $this->users->deactivate($tenant, $user);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'CANNOT_DEACTIVATE_OWNER');
        }

        return $this->success($row, 'User deactivated successfully.');
    }

    public function reactivate(Tenant $tenant, User $user): JsonResponse
    {
        try {
            $row = $this->users->reactivate($tenant, $user);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'CANNOT_REACTIVATE_OWNER');
        }

        return $this->success($row, 'User reactivated successfully.');
    }

    public function resetPassword(Tenant $tenant, User $user): JsonResponse
    {
        $temporaryPassword = $this->users->resetPassword($tenant, $user);

        return $this->success(['temporary_password' => $temporaryPassword]);
    }
}
