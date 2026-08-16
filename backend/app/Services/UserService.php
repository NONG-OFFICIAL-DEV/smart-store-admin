<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService extends BaseService
{
    public function __construct(
        UserRepositoryInterface $repository,
        private PasswordService $passwordService,
        private RefreshTokenService $refreshTokenService
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
     *
     * An email change here is an admin acting on someone else's account, so
     * it's treated with the same care as the self-service email change:
     * the new address is unverified until proven otherwise, and every
     * existing session for that user is killed (their next silent-refresh
     * attempt fails, forcing a fresh login) since an admin-initiated
     * identity change is exactly the kind of event a live session should
     * not silently survive.
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

        $emailChanged = array_key_exists('email', $data) && $data['email'] !== $user->email;
        $oldEmail = $user->email;

        if ($emailChanged) {
            $data['email_verified_at'] = null;
        }

        return DB::transaction(function () use ($user, $data, $emailChanged, $oldEmail) {
            $updated = $this->repository->update($user, $data);

            if ($emailChanged) {
                $this->refreshTokenService->revokeAllForUser($updated->id);

                ActivityLog::log(
                    action: 'user.email_changed_by_admin',
                    entity: $updated,
                    payload: ['old_email' => $oldEmail, 'new_email' => $updated->email],
                    description: "Admin changed {$updated->full_name}'s login email from {$oldEmail} to {$updated->email}"
                );
            }

            return $updated;
        });
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
