<?php

namespace Tests\Feature\Notifications;

use App\Models\Notification;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * TenantScope only ever enforced the tenant boundary — nothing restricted
 * *which user within the tenant* a notification is visible to, and this
 * resource has no permission gate at all (any authenticated user could
 * hit GET /notifications). Any staff member could see every other
 * staff member's and every other role's notifications. Fixed via
 * NotificationRepository::scopeToRecipient() (used by both index() and a
 * new assertVisible() guard on show/markRead/destroy, which previously
 * only relied on route-model-binding + TenantScope and so still let a
 * user reach another user's notification directly by id even though it
 * was hidden from their list).
 */
class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithOwner(string $name): array
    {
        $owner = User::create([
            'email' => strtolower($name).'@example.test',
            'first_name' => $name,
            'last_name' => 'Owner',
            'is_super_admin' => false,
        ]);

        $tenant = Tenant::create([
            'name' => $name,
            'slug' => strtolower($name).'-'.substr((string) $owner->id, 0, 8),
            'owner_user_id' => $owner->id,
        ]);

        return [$tenant, $owner];
    }

    private function makeStaffUser(Tenant $tenant, string $name, ?Role $role = null): User
    {
        $user = User::create([
            'email' => strtolower($name).'@example.test',
            'first_name' => $name,
            'last_name' => 'Staffer',
        ]);

        $branch = \App\Models\Branch::create([
            'tenant_id' => $tenant->id, 'name' => $name.' Branch', 'address_line1' => 'x', 'city' => 'y',
        ]);

        Staff::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role_id' => ($role ?? Role::create(['tenant_id' => $tenant->id, 'name' => $name.' Role']))->id,
        ]);

        return $user;
    }

    public function test_a_user_only_sees_notifications_targeted_at_them(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        $userA = $this->makeStaffUser($tenant, 'Alice');
        $userB = $this->makeStaffUser($tenant, 'Bob');

        Notification::create(['tenant_id' => $tenant->id, 'user_id' => $userA->id, 'type' => 'info', 'title' => 'For Alice', 'body' => 'x']);
        Notification::create(['tenant_id' => $tenant->id, 'user_id' => $userB->id, 'type' => 'info', 'title' => 'For Bob', 'body' => 'x']);

        Auth::login($userA);
        $service = $this->app->make(NotificationService::class);
        $results = $service->list([], $userA);

        $this->assertSame(1, $results->total());
        $this->assertSame('For Alice', $results->first()->title);
    }

    public function test_a_broadcast_notification_with_no_user_or_role_is_visible_to_everyone_in_tenant(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantB');
        $userA = $this->makeStaffUser($tenant, 'Alice');

        Notification::create(['tenant_id' => $tenant->id, 'type' => 'info', 'title' => 'Broadcast', 'body' => 'x']);

        Auth::login($userA);
        $service = $this->app->make(NotificationService::class);
        $results = $service->list([], $userA);

        $this->assertSame(1, $results->total());
    }

    public function test_a_role_targeted_notification_is_visible_only_to_that_role(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantC');
        $managerRole = Role::create(['tenant_id' => $tenant->id, 'name' => 'Manager']);
        $manager = $this->makeStaffUser($tenant, 'Mona', $managerRole);
        $cashier = $this->makeStaffUser($tenant, 'Cid');

        Notification::create(['tenant_id' => $tenant->id, 'role_id' => $managerRole->id, 'type' => 'info', 'title' => 'Managers only', 'body' => 'x']);

        Auth::login($manager);
        $service = $this->app->make(NotificationService::class);
        $this->assertSame(1, $service->list([], $manager)->total());

        Auth::login($cashier);
        $this->assertSame(0, $service->list([], $cashier)->total());
    }

    /**
     * Regression test for a real bug: `read_at` was missing from
     * Notification::$fillable, so markRead()'s instance-level
     * `$model->update(['read_at' => ...])` silently no-op'd — every "mark
     * as read" click across the app looked like it worked but never
     * persisted (confirmed via tinker before this test existed).
     */
    public function test_markRead_actually_persists_read_at(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantG');
        $userA = $this->makeStaffUser($tenant, 'Alice');

        $notification = Notification::create(['tenant_id' => $tenant->id, 'user_id' => $userA->id, 'type' => 'info', 'title' => 'For Alice', 'body' => 'x']);

        $service = $this->app->make(NotificationService::class);
        $service->markRead($notification);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_assertVisible_rejects_another_users_notification(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantD');
        $userA = $this->makeStaffUser($tenant, 'Alice');
        $userB = $this->makeStaffUser($tenant, 'Bob');

        $notification = Notification::create(['tenant_id' => $tenant->id, 'user_id' => $userB->id, 'type' => 'info', 'title' => 'For Bob', 'body' => 'x']);

        Auth::login($userA);
        $service = $this->app->make(NotificationService::class);

        $this->expectException(ModelNotFoundException::class);
        $service->assertVisible($notification, $userA);
    }

    public function test_unreadCount_only_counts_notifications_visible_to_that_user(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantF');
        $userA = $this->makeStaffUser($tenant, 'Alice');
        $userB = $this->makeStaffUser($tenant, 'Bob');

        Notification::create(['tenant_id' => $tenant->id, 'user_id' => $userA->id, 'type' => 'info', 'title' => 'For Alice 1', 'body' => 'x']);
        $readOne = Notification::create(['tenant_id' => $tenant->id, 'user_id' => $userA->id, 'type' => 'info', 'title' => 'For Alice 2', 'body' => 'x']);
        $readOne->update(['read_at' => now()]);
        Notification::create(['tenant_id' => $tenant->id, 'user_id' => $userB->id, 'type' => 'info', 'title' => 'For Bob', 'body' => 'x']);

        $service = $this->app->make(NotificationService::class);

        $this->assertSame(1, $service->unreadCount($userA));
    }

    public function test_markAllRead_only_marks_notifications_visible_to_that_user(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantE');
        $userA = $this->makeStaffUser($tenant, 'Alice');
        $userB = $this->makeStaffUser($tenant, 'Bob');

        $forA = Notification::create(['tenant_id' => $tenant->id, 'user_id' => $userA->id, 'type' => 'info', 'title' => 'For Alice', 'body' => 'x']);
        $forB = Notification::create(['tenant_id' => $tenant->id, 'user_id' => $userB->id, 'type' => 'info', 'title' => 'For Bob', 'body' => 'x']);

        Auth::login($userA);
        $service = $this->app->make(NotificationService::class);
        $service->markAllRead($userA);

        $this->assertNotNull($forA->refresh()->read_at);
        $this->assertNull($forB->refresh()->read_at);
    }
}
