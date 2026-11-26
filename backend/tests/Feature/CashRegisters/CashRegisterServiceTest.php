<?php

namespace Tests\Feature\CashRegisters;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CashDrawerService;
use App\Services\CashRegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class CashRegisterServiceTest extends TestCase
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

    private function makeBranch(Tenant $tenant, string $name): Branch
    {
        return Branch::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'address_line1' => '123 Main St',
            'city' => 'Phnom Penh',
        ]);
    }

    public function test_create_and_update_a_register(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantReg1');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant, 'Branch 1');

        $service = $this->app->make(CashRegisterService::class);
        $register = $service->create([
            'branch_id' => $branch->id,
            'name' => 'Front Counter',
            'description' => 'Main register at the entrance',
        ]);

        $this->assertSame('Front Counter', $register->name);
        $this->assertTrue($register->is_active);

        $updated = $service->update($register, ['is_active' => false]);
        $this->assertFalse($updated->is_active);
    }

    public function test_a_tenant_only_sees_their_own_registers(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantReg2');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantReg3');

        Auth::login($ownerB);
        $branchB = $this->makeBranch($tenantB, 'Branch B');
        $service = $this->app->make(CashRegisterService::class);
        $service->create(['branch_id' => $branchB->id, 'name' => 'Register B']);

        Auth::login($ownerA);
        $results = $service->list([]);

        $this->assertSame(0, $results->total());
    }

    public function test_has_open_shift_reflects_the_registers_live_cash_drawer(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantReg4');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant, 'Branch C');

        $registerService = $this->app->make(CashRegisterService::class);
        $register = $registerService->create(['branch_id' => $branch->id, 'name' => 'Drive-Thru']);

        $this->assertFalse($register->hasOpenShift());

        $role = \App\Models\Role::create(['tenant_id' => $tenant->id, 'name' => 'Cashier Role']);
        $staffUser = User::create(['email' => 'cashier-reg4@example.test', 'first_name' => 'C', 'last_name' => 'Cashier']);
        $staff = \App\Models\Staff::create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id,
            'user_id' => $staffUser->id, 'role_id' => $role->id,
        ]);

        $drawerService = $this->app->make(CashDrawerService::class);
        $drawerService->open([
            'branch_id' => $branch->id, 'cash_register_id' => $register->id,
            'staff_id' => $staff->id, 'opening_float' => 50,
        ]);

        $this->assertTrue($register->fresh()->hasOpenShift());
    }
}
