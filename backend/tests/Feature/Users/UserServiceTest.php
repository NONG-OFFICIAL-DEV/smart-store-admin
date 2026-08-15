<?php

namespace Tests\Feature\Users;

use App\Models\Tenant;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * UserController is superadmin-only platform user management — User has no
 * tenant_id by design (it spans tenants and super admins), so there is no
 * TenantScope concern here. What matters is the three business-rule guards
 * that used to live inline in the controller/model: password only ever set
 * on create, tenant owners can't be deactivated/deleted through this
 * screen, and super-admin accounts can't be deleted at all.
 */
class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_applies_a_temporary_password_and_never_stores_it_plain(): void
    {
        $service = $this->app->make(UserService::class);

        $user = $service->create([
            'first_name' => 'New',
            'last_name' => 'Hire',
            'email' => 'new.hire@example.test',
            'password' => 'Sup3rSecret',
        ]);

        $this->assertTrue($user->must_change_password);
        $this->assertNotSame('Sup3rSecret', $user->password_hash);
    }

    public function test_create_without_a_password_leaves_the_account_passwordless(): void
    {
        $service = $this->app->make(UserService::class);

        $user = $service->create([
            'first_name' => 'No',
            'last_name' => 'Password',
            'email' => 'no.password@example.test',
        ]);

        $this->assertNull($user->password_hash);
    }

    public function test_deactivating_a_tenant_owner_is_rejected(): void
    {
        $owner = User::create(['first_name' => 'Owner', 'last_name' => 'One', 'email' => 'owner@example.test']);
        Tenant::create(['name' => 'TenantA', 'slug' => 'tenant-a', 'owner_user_id' => $owner->id]);

        $service = $this->app->make(UserService::class);

        $this->expectException(ValidationException::class);
        $service->update($owner, ['is_active' => false]);
    }

    public function test_deactivating_a_non_owner_is_allowed(): void
    {
        $staffUser = User::create(['first_name' => 'Staff', 'last_name' => 'Person', 'email' => 'staff@example.test']);

        $service = $this->app->make(UserService::class);
        $updated = $service->update($staffUser, ['is_active' => false]);

        $this->assertFalse($updated->is_active);
    }

    public function test_deleting_a_super_admin_is_rejected(): void
    {
        $admin = User::create(['first_name' => 'Admin', 'last_name' => 'User', 'email' => 'admin@example.test', 'is_super_admin' => true]);

        $service = $this->app->make(UserService::class);

        $this->expectException(ValidationException::class);
        $service->delete($admin);
    }

    public function test_deleting_a_tenant_owner_is_rejected(): void
    {
        $owner = User::create(['first_name' => 'Owner', 'last_name' => 'Two', 'email' => 'owner2@example.test']);
        Tenant::create(['name' => 'TenantB', 'slug' => 'tenant-b', 'owner_user_id' => $owner->id]);

        $service = $this->app->make(UserService::class);

        $this->expectException(ValidationException::class);
        $service->delete($owner);
    }

    public function test_deleting_a_regular_user_goes_through_the_repository(): void
    {
        $user = User::create(['first_name' => 'Regular', 'last_name' => 'User', 'email' => 'regular@example.test']);

        $service = $this->app->make(UserService::class);
        $service->delete($user);

        $this->assertNull(User::find($user->id));
    }

    public function test_reset_password_generates_a_new_temporary_password(): void
    {
        $user = User::create(['first_name' => 'Reset', 'last_name' => 'Me', 'email' => 'reset.me@example.test']);
        $service = $this->app->make(UserService::class);

        $plain = $service->resetPassword($user);

        $this->assertNotEmpty($plain);
        $this->assertTrue($user->refresh()->must_change_password);
    }
}
