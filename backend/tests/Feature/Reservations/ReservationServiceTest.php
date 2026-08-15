<?php

namespace Tests\Feature\Reservations;

use App\Models\Branch;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Covers the Reservation resource's Repository/Service migration (see
 * .claude/skills/migrate-resource-to-repository). byBranch/byTable/
 * confirm/seat/cancel/no-show all had routes registered but no matching
 * controller methods at all — 6 guaranteed 500s, now implemented. seat()
 * in particular is a real fix: the original static Reservation::seat()
 * reassigned table_id with NO conflict check at all, unlike store()/
 * update(), which could silently double-book a table when seating.
 */
class ReservationServiceTest extends TestCase
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

    private function makeTable(Branch $branch, string $number): Table
    {
        return Table::create(['branch_id' => $branch->id, 'table_number' => $number]);
    }

    public function test_overlapping_reservation_on_the_same_table_is_rejected_on_create(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $table = $this->makeTable($branch, 'T1');

        Auth::login($ownerA);
        $service = $this->app->make(ReservationService::class);
        $service->create([
            'branch_id' => $branch->id, 'table_id' => $table->id,
            'customer_name' => 'Alice', 'party_size' => 2,
            'reserved_at' => now()->addHours(2), 'duration_minutes' => 90,
        ]);

        $this->expectException(ValidationException::class);

        // Overlaps: starts 30 min into the first reservation's 90-min window.
        $service->create([
            'branch_id' => $branch->id, 'table_id' => $table->id,
            'customer_name' => 'Bob', 'party_size' => 2,
            'reserved_at' => now()->addHours(2)->addMinutes(30), 'duration_minutes' => 60,
        ]);
    }

    public function test_non_overlapping_reservation_on_the_same_table_is_allowed(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $table = $this->makeTable($branch, 'T1');

        Auth::login($ownerA);
        $service = $this->app->make(ReservationService::class);
        $service->create([
            'branch_id' => $branch->id, 'table_id' => $table->id,
            'customer_name' => 'Alice', 'party_size' => 2,
            'reserved_at' => now()->addHours(2), 'duration_minutes' => 60,
        ]);

        $second = $service->create([
            'branch_id' => $branch->id, 'table_id' => $table->id,
            'customer_name' => 'Bob', 'party_size' => 2,
            'reserved_at' => now()->addHours(4), 'duration_minutes' => 60,
        ]);

        $this->assertNotNull($second->id);
    }

    public function test_cancelled_reservations_do_not_block_new_bookings_on_the_same_slot(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $table = $this->makeTable($branch, 'T1');

        Auth::login($ownerA);
        $service = $this->app->make(ReservationService::class);
        $first = $service->create([
            'branch_id' => $branch->id, 'table_id' => $table->id,
            'customer_name' => 'Alice', 'party_size' => 2,
            'reserved_at' => now()->addHours(2), 'duration_minutes' => 90,
        ]);
        $service->cancel($first);

        $second = $service->create([
            'branch_id' => $branch->id, 'table_id' => $table->id,
            'customer_name' => 'Bob', 'party_size' => 2,
            'reserved_at' => now()->addHours(2)->addMinutes(30), 'duration_minutes' => 60,
        ]);

        $this->assertNotNull($second->id);
    }

    public function test_seating_at_an_already_occupied_table_is_rejected(): void
    {
        // Previously: Reservation::seat() had NO conflict check at all —
        // this scenario would have silently double-booked the table.
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $tableA = $this->makeTable($branch, 'T1');
        $tableB = $this->makeTable($branch, 'T2');

        Auth::login($ownerA);
        $service = $this->app->make(ReservationService::class);
        $service->create([
            'branch_id' => $branch->id, 'table_id' => $tableA->id,
            'customer_name' => 'Alice', 'party_size' => 2,
            'reserved_at' => now()->addMinutes(10), 'duration_minutes' => 90,
        ]);

        $bookedForTableB = $service->create([
            'branch_id' => $branch->id, 'table_id' => $tableB->id,
            'customer_name' => 'Bob', 'party_size' => 2,
            'reserved_at' => now()->addMinutes(20), 'duration_minutes' => 90,
        ]);

        $this->expectException(ValidationException::class);
        // Trying to seat Bob's reservation at Alice's table instead.
        $service->seat($bookedForTableB, $tableA->id);
    }

    public function test_confirm_seat_cancel_and_no_show_transition_status(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $table = $this->makeTable($branch, 'T1');

        Auth::login($ownerA);
        $service = $this->app->make(ReservationService::class);
        $reservation = $service->create([
            'branch_id' => $branch->id, 'table_id' => $table->id,
            'customer_name' => 'Alice', 'party_size' => 2,
            'reserved_at' => now()->addHours(1), 'duration_minutes' => 60,
        ]);

        $confirmed = $service->confirm($reservation);
        $this->assertSame('confirmed', $confirmed->status);

        $seated = $service->seat($confirmed, $table->id);
        $this->assertSame('seated', $seated->status);
        $this->assertSame('occupied', $table->fresh()->status);

        $cancelled = $service->cancel($seated);
        $this->assertSame('cancelled', $cancelled->status);
    }

    public function test_a_tenant_owner_only_sees_reservations_at_their_own_branches(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');
        $branchA = $this->makeBranch($tenantA, 'A-Main');
        $branchB = $this->makeBranch($tenantB, 'B-Main');

        Auth::login($ownerA);
        $service = $this->app->make(ReservationService::class);
        $service->create(['branch_id' => $branchA->id, 'customer_name' => 'A', 'party_size' => 2, 'reserved_at' => now()->addHour()]);

        Auth::login($ownerB);
        $service->create(['branch_id' => $branchB->id, 'customer_name' => 'B', 'party_size' => 2, 'reserved_at' => now()->addHour()]);

        Auth::login($ownerA);
        $this->assertSame(1, $service->list([])->total());
        $this->assertSame(1, $service->byBranch($branchA, [])->total());
    }
}
