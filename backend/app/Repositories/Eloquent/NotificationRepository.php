<?php

namespace App\Repositories\Eloquent;

use App\Models\Notification;
use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class NotificationRepository extends BaseRepository implements NotificationRepositoryInterface
{
    protected array $searchable = ['title', 'type'];

    public function __construct(Notification $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (array_key_exists('unread_only', $filters) && filter_var($filters['unread_only'], FILTER_VALIDATE_BOOLEAN)) {
            $query->whereNull('read_at');
        }

        if (! empty($filters['for_user'])) {
            $this->scopeToRecipient($query, $filters['for_user']);
        }
    }

    /**
     * TenantScope only enforces the tenant boundary — nothing previously
     * restricted *which user within the tenant* a notification is visible
     * to, so any authenticated user (any permission level; this resource
     * has no permission gate at all) could see every other user's and
     * every other role's notifications via a plain GET /notifications.
     * A notification is visible to a user if it directly targets them,
     * targets their role, or targets neither (a tenant/branch-wide
     * broadcast). Public so the Service can build a bulk-update query
     * (e.g. markAllRead) with the same visibility rule as listing.
     */
    public function queryVisibleTo(User $user): Builder
    {
        $query = $this->query();
        $this->scopeToRecipient($query, $user);

        return $query;
    }

    private function scopeToRecipient(Builder $query, User $user): void
    {
        $roleId = $user->staff?->role_id;

        $query->where(function (Builder $q) use ($user, $roleId) {
            $q->where('user_id', $user->id)
                ->orWhere(function (Builder $q2) use ($roleId) {
                    $q2->whereNull('user_id');
                    if ($roleId) {
                        $q2->where(fn($q3) => $q3->whereNull('role_id')->orWhere('role_id', $roleId));
                    } else {
                        $q2->whereNull('role_id');
                    }
                });
        });
    }
}
