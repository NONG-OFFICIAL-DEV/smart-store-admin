<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class NotificationService extends BaseService
{
    public function __construct(
        NotificationRepositoryInterface $repository,
        private TelegramService $telegram,
    ) {
        parent::__construct($repository);
    }

    public function getPreferences(User $user): array
    {
        return [
            'notify_system' => $user->notify_system,
            'notify_email' => $user->notify_email,
            'notify_telegram' => $user->notify_telegram,
            'telegram_linked' => (bool) $user->telegram_chat_id,
        ];
    }

    public function updatePreferences(User $user, array $data): User
    {
        // A telegram preference with no linked chat yet can't actually
        // deliver anything — silently drop it rather than storing an
        // opt-in that has no effect until the user links via the deep link.
        if (($data['notify_telegram'] ?? false) && ! $user->telegram_chat_id) {
            unset($data['notify_telegram']);
        }

        $user->update($data);

        return $user->refresh();
    }

    public function createTelegramLinkUrl(User $user): string
    {
        return $this->telegram->generateLinkUrl($user);
    }

    public function unlinkTelegram(User $user): User
    {
        return $this->telegram->unlink($user);
    }

    public function list(array $filters, User $forUser): LengthAwarePaginator
    {
        return $this->repository->paginateServer(array_merge($filters, ['for_user' => $forUser]));
    }

    public function unreadCount(User $user): int
    {
        return $this->repository->queryVisibleTo($user)->whereNull('read_at')->count();
    }

    /**
     * Route-model-binding on show/markRead/destroy only enforces the
     * tenant boundary (via TenantScope) — without this, any user could
     * still reach another user's or role's notification directly by id
     * even though index() correctly hides it from the list.
     */
    public function assertVisible(Notification $notification, User $user): void
    {
        $visible = $this->repository->queryVisibleTo($user)->whereKey($notification->id)->exists();

        if (! $visible) {
            throw new ModelNotFoundException();
        }
    }

    public function markRead(Notification $notification): Notification
    {
        return $this->repository->update($notification, ['read_at' => now()]);
    }

    /**
     * Marks every notification visible to this user as read. Note: read_at
     * is a single column on the row itself, not a per-recipient join table
     * — marking a role-wide or tenant-wide broadcast as read here marks it
     * read for every recipient of that broadcast, not just this user. This
     * mirrors the schema as it exists today; a true per-recipient read
     * state would need a separate pivot table.
     */
    public function markAllRead(User $user): int
    {
        return $this->repository->queryVisibleTo($user)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function delete(Notification $notification): bool
    {
        return $this->repository->delete($notification);
    }
}
