<?php

namespace Tests\Feature\Notifications;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Central real-time-push hook — NotificationObserver::created() dispatches
 * NotificationCreated for every user-targeted Notification row, regardless
 * of which of this app's ad-hoc call sites created it (there's no single
 * NotificationService::send() everything goes through here — the observer
 * is what makes that unnecessary).
 */
class NotificationBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithOwner(string $name): array
    {
        $owner = User::create([
            'email' => strtolower($name).'@example.test',
            'first_name' => $name,
            'last_name' => 'Owner',
        ]);

        $tenant = Tenant::create([
            'name' => $name,
            'slug' => strtolower($name).'-'.substr((string) $owner->id, 0, 8),
            'owner_user_id' => $owner->id,
        ]);

        return [$tenant, $owner];
    }

    public function test_creating_a_user_targeted_notification_dispatches_the_broadcast_event(): void
    {
        Event::fake([NotificationCreated::class]);
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');

        $notification = Notification::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'type' => 'info',
            'title' => 'For Owner',
            'body' => 'x',
        ]);

        Event::assertDispatched(
            NotificationCreated::class,
            fn (NotificationCreated $event) => $event->notification->id === $notification->id
        );
    }

    public function test_a_broadcast_or_role_targeted_notification_does_not_dispatch(): void
    {
        Event::fake([NotificationCreated::class]);
        [$tenant] = $this->makeTenantWithOwner('TenantB');

        Notification::create([
            'tenant_id' => $tenant->id,
            'type' => 'info',
            'title' => 'Tenant-wide broadcast',
            'body' => 'x',
        ]);

        Event::assertNotDispatched(NotificationCreated::class);
    }

    public function test_disabling_the_system_channel_suppresses_the_live_push_but_the_row_still_exists(): void
    {
        Event::fake([NotificationCreated::class]);
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantH');
        $owner->update(['notify_system' => false]);

        $notification = Notification::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'type' => 'info',
            'title' => 'Silenced push',
            'body' => 'x',
        ]);

        Event::assertNotDispatched(NotificationCreated::class);
        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
    }

    public function test_the_broadcast_channel_and_payload_are_correct(): void
    {
        Event::fake([NotificationCreated::class]);
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantC');
        $notification = Notification::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'type' => 'low_stock',
            'title' => 'Low stock',
            'body' => 'x',
        ]);

        $event = new NotificationCreated($notification);

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertSame("private-App.Models.User.{$owner->id}", $channels[0]->name);
        $this->assertSame('notification.created', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertSame($notification->id, $payload['id']);
        $this->assertSame('low_stock', $payload['type']);
    }
}
