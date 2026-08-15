<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class UserService extends BaseService
{
    public function __construct(
        UserRepositoryInterface $repository,
        private PasswordService $passwordService
    ) {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function create(array $data): User
    {
        $password = $data['password'] ?? null;
        unset($data['password']);

        $user = $this->repository->create($data);

        if ($password) {
            $this->passwordService->applyPassword($user, $password, temporary: true);
        }

        return $user->refresh();
    }

    /**
     * The tenant owner can never be deactivated through this screen — they
     * must be transferred out of ownership first. Password is never touched
     * here; that's the dedicated resetPassword() flow.
     */
    public function update(User $user, array $data): User
    {
        if (array_key_exists('is_active', $data)
            && ! $data['is_active']
            && Tenant::where('owner_user_id', $user->id)->exists()
        ) {
            throw ValidationException::withMessages([
                'is_active' => 'Cannot deactivate the tenant owner. Transfer ownership first.',
            ]);
        }

        return $this->repository->update($user, $data);
    }

    public function delete(User $user): void
    {
        if ($user->is_super_admin) {
            throw ValidationException::withMessages([
                'id' => 'Admin user cannot be deleted.',
            ]);
        }

        if (Tenant::where('owner_user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'id' => 'Cannot delete the tenant owner. Transfer ownership first.',
            ]);
        }

        $this->repository->delete($user);
    }

    public function resetPassword(User $user): string
    {
        return $this->passwordService->adminReset($user);
    }
}
