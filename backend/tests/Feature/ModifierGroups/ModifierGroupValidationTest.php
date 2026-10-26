<?php

namespace Tests\Feature\ModifierGroups;

use App\Http\Requests\StoreModifierGroupRequest;
use App\Http\Requests\UpdateModifierGroupRequest;
use App\Models\ModifierGroup;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Covers the two FormRequest-level rules that previously didn't exist:
 * at-least-one-choice (already had a rule, re-verified here end-to-end
 * through withValidator) and the new "a required group needs at least one
 * AVAILABLE choice" check. Exercised directly against the FormRequest
 * (there's no HTTP-route-level testing convention in this codebase — every
 * other test calls Services/Controllers directly), since
 * ModifierGroupServiceTest calls the Service with plain arrays and never
 * passes through FormRequest validation at all.
 */
class ModifierGroupValidationTest extends TestCase
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

    private function runStoreValidation(array $data)
    {
        $request = StoreModifierGroupRequest::create('/modifier-groups', 'POST', $data);
        $validator = Validator::make($request->all(), $request->rules());
        $request->withValidator($validator);

        return $validator;
    }

    private function runUpdateValidation(array $data, ?ModifierGroup $group = null)
    {
        $request = UpdateModifierGroupRequest::create('/modifier-groups/x', 'PUT', $data);
        if ($group) {
            $request->setRouteResolver(fn () => new class($group) {
                public function __construct(public $group) {}
                public function parameter($name, $default = null)
                {
                    return $this->group;
                }
            });
        }
        $validator = Validator::make($request->all(), $request->rules());
        $request->withValidator($validator);

        return $validator;
    }

    public function test_store_rejects_a_group_with_no_choices(): void
    {
        [, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);

        $validator = $this->runStoreValidation([
            'name' => 'Sugar',
            'selection_type' => 'single',
            'options' => [],
        ]);

        $this->assertTrue($validator->fails());
    }

    public function test_store_rejects_a_required_group_with_no_available_choices(): void
    {
        [, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);

        $validator = $this->runStoreValidation([
            'name' => 'Free',
            'selection_type' => 'multiple',
            'max_selections' => 3,
            'is_required' => true,
            'options' => [
                ['name' => 'Ice', 'is_available' => false],
                ['name' => 'Sugar', 'is_available' => false],
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString(
            "can't be ordered until a choice is available",
            implode(' ', $validator->errors()->get('options'))
        );
    }

    public function test_store_allows_a_required_group_with_one_available_choice(): void
    {
        [, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);

        $validator = $this->runStoreValidation([
            'name' => 'Sugar',
            'selection_type' => 'single',
            'is_required' => true,
            'options' => [
                ['name' => '0%', 'is_available' => true],
                ['name' => '50%', 'is_available' => false],
            ],
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_update_falls_back_to_the_existing_groups_is_required_when_not_resent(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);

        $group = ModifierGroup::create([
            'tenant_id' => $tenant->id,
            'name' => 'Sugar',
            'selection_type' => 'single',
            'is_required' => true,
        ]);

        // is_required is deliberately omitted from this payload — the
        // request must still treat the group as required (it already was).
        $validator = $this->runUpdateValidation([
            'options' => [
                ['name' => '0%', 'is_available' => false],
                ['name' => '50%', 'is_available' => false],
            ],
        ], $group);

        $this->assertTrue($validator->fails());
    }

    public function test_update_allows_a_non_required_group_to_have_all_choices_unavailable(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);

        $group = ModifierGroup::create([
            'tenant_id' => $tenant->id,
            'name' => 'Toppings',
            'selection_type' => 'multiple',
            'is_required' => false,
        ]);

        $validator = $this->runUpdateValidation([
            'options' => [
                ['name' => 'Pearl', 'is_available' => false],
            ],
        ], $group);

        $this->assertFalse($validator->fails());
    }
}
