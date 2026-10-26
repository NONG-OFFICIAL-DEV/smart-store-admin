<?php

namespace Tests\Feature\ModifierGroups;

use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * The read-only `modifier-groups:audit` command — confirms it finds each of
 * the three issue categories and, just as importantly, never writes
 * anything (the whole point is "show me the list, don't change data").
 */
class AuditModifierGroupsTest extends TestCase
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

    public function test_it_lists_all_three_issue_categories_and_changes_nothing(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);

        $empty = ModifierGroup::create(['tenant_id' => $tenant->id, 'name' => 'Empty Group']);

        $requiredNoAvailable = ModifierGroup::create([
            'tenant_id' => $tenant->id, 'name' => 'Free', 'is_required' => true, 'selection_type' => 'multiple',
        ]);
        ModifierOption::create(['group_id' => $requiredNoAvailable->id, 'name' => 'Ice', 'is_available' => false]);

        $maxTooHigh = ModifierGroup::create([
            'tenant_id' => $tenant->id, 'name' => 'Pearl', 'selection_type' => 'multiple', 'max_selections' => 3,
        ]);
        ModifierOption::create(['group_id' => $maxTooHigh->id, 'name' => 'Boba', 'is_available' => true]);

        $healthy = ModifierGroup::create(['tenant_id' => $tenant->id, 'name' => 'Sugar', 'selection_type' => 'single']);
        ModifierOption::create(['group_id' => $healthy->id, 'name' => '100%', 'is_available' => true]);

        $this->artisan('modifier-groups:audit')
            ->expectsOutputToContain('Empty Group')
            ->expectsOutputToContain('Free')
            ->expectsOutputToContain('Pearl')
            ->assertExitCode(0);

        $this->assertDatabaseCount('modifier_groups', 4);
        $this->assertDatabaseCount('modifier_options', 3);
        $this->assertFalse($empty->fresh()->options()->exists());
        $this->assertFalse($requiredNoAvailable->fresh()->options->first()->is_available);
    }

    public function test_it_reports_no_issues_when_everything_is_healthy(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);

        $group = ModifierGroup::create(['tenant_id' => $tenant->id, 'name' => 'Sugar', 'selection_type' => 'single']);
        ModifierOption::create(['group_id' => $group->id, 'name' => '100%', 'is_available' => true]);

        $this->artisan('modifier-groups:audit')
            ->expectsOutputToContain('No data-integrity issues found.')
            ->assertExitCode(0);
    }
}
