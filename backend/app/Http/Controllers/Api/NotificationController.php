<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    public function __construct(private NotificationService $notifications)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->notifications->list(
            $request->only(['search', 'sortBy', 'sortDesc', 'perPage', 'type', 'unread_only']),
            $request->user(),
        );

        return $this->success(
            NotificationResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'unread_count' => $this->notifications->unreadCount($request->user()),
            ]
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success(['count' => $this->notifications->unreadCount($request->user())]);
    }

    public function show(Request $request, Notification $notification): JsonResponse
    {
        $this->notifications->assertVisible($notification, $request->user());

        return $this->success(new NotificationResource($notification));
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        $this->notifications->assertVisible($notification, $request->user());
        $notification = $this->notifications->markRead($notification);

        return $this->success(new NotificationResource($notification), 'Notification marked as read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $this->notifications->markAllRead($request->user());

        return $this->success(['updated' => $count], 'All notifications marked as read.');
    }

    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        $this->notifications->assertVisible($notification, $request->user());
        $this->notifications->delete($notification);

        return $this->noContent('Notification deleted successfully.');
    }

    public function preferences(Request $request): JsonResponse
    {
        return $this->success($this->notifications->getPreferences($request->user()));
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'notify_system' => 'sometimes|boolean',
            'notify_email' => 'sometimes|boolean',
            'notify_telegram' => 'sometimes|boolean',
        ]);

        $user = $this->notifications->updatePreferences($request->user(), $data);

        return $this->success($this->notifications->getPreferences($user), 'Preferences updated.');
    }

    public function telegramLinkUrl(Request $request): JsonResponse
    {
        return $this->success(['url' => $this->notifications->createTelegramLinkUrl($request->user())]);
    }

    public function unlinkTelegram(Request $request): JsonResponse
    {
        $this->notifications->unlinkTelegram($request->user());

        return $this->success($this->notifications->getPreferences($request->user()), 'Telegram unlinked.');
    }
}
