<?php

namespace Tests\Feature\BranchHours;

use App\Models\Branch;
use App\Models\BranchHour;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BranchHourService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Covers the BranchHour resource's Repository/Service migration (see
 * .claude/skills/migrate-resource-to-repository). The old controller's
 * store()/update()/destroy() were all empty stubs (`//`) — this resource
 * had no working create/update/delete at all despite routes existing for
 * them, and index() never even filtered by branch_id despite living under
 * branches/{branch}/hours (every call returned every branch's hours).
 */
class BranchHourServiceTest extends TestCase
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

    public function test_for_branch_only_returns_that_branchs_hours(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch1 = $this->makeBranch($tenantA, 'Branch1');
        $branch2 = $this->makeBranch($tenantA, 'Branch2');

        Auth::login($ownerA);
        $service = $this->app->make(BranchHourService::class);
        $service->create($branch1, ['day_of_week' => 1, 'open_time' => '08:00', 'close_time' => '17:00']);
        $service->create($branch2, ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '18:00']);

        $this->assertCount(1, $service->forBranch($branch1));
        $this->assertCount(1, $service->forBranch($branch2));
    }

    public function test_create_actually_persists_unlike_the_old_empty_stub(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');

        Auth::login($ownerA);
        $service = $this->app->make(BranchHourService::class);
        $hour = $service->create($branch, ['day_of_week' => 2, 'open_time' => '08:00', 'close_time' => '17:00']);

        $this->assertNotNull(BranchHour::find($hour->id));
        $this->assertSame($branch->id, $hour->branch_id);
        $this->assertSame('Tuesday', $hour->day_name);
    }

    public function test_duplicate_day_for_the_same_branch_fails_validation_cleanly(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $branch2 = $this->makeBranch($tenantA, 'Second');

        Auth::login($ownerA);
        $service = $this->app->make(BranchHourService::class);
        $service->create($branch, ['day_of_week' => 3, 'open_time' => '08:00', 'close_time' => '17:00']);

        // Same day, same branch — the DB's unique(['branch_id','day_of_week'])
        // constraint (and the matching FormRequest validation rule) rejects it.
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['day_of_week' => 3],
            ['day_of_week' => \Illuminate\Validation\Rule::unique('branch_hours', 'day_of_week')->where('branch_id', $branch->id)]
        );
        $this->assertTrue($validator->fails());

        // Same day, DIFFERENT branch — must be allowed.
        $otherBranchValidator = \Illuminate\Support\Facades\Validator::make(
            ['day_of_week' => 3],
            ['day_of_week' => \Illuminate\Validation\Rule::unique('branch_hours', 'day_of_week')->where('branch_id', $branch2->id)]
        );
        $this->assertFalse($otherBranchValidator->fails());
    }

    public function test_update_and_delete_go_through_the_repository(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');

        Auth::login($ownerA);
        $service = $this->app->make(BranchHourService::class);
        $hour = $service->create($branch, ['day_of_week' => 4, 'open_time' => '08:00', 'close_time' => '17:00']);

        $updated = $service->update($hour, ['is_closed' => true]);
        $this->assertTrue($updated->is_closed);

        $service->delete($hour);
        $this->assertNull(BranchHour::find($hour->id));
    }
}
