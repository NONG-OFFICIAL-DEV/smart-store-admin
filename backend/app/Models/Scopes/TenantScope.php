<?php

namespace App\Models\Scopes;

use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Tables with neither tenant_id nor branch_id of their own, tenant-owned
     * only indirectly through a belongsTo relation whose own tenant_id is
     * authoritative — add an entry here instead of a new elseif branch when
     * the shape is a plain `whereHas($relation, where tenant_id = X)`. Each
     * of these previously had no #[ScopedBy] at all and was reachable by a
     * flat/shallow route with no tenant context in the URL, so any
     * authenticated user in any tenant could view/edit/delete another
     * tenant's row directly by id.
     */
    private const INDIRECT_TENANT_RELATIONS = [
        'modifier_options'    => 'group',      // -> modifier_groups.tenant_id
        'coupons'             => 'promotion',  // -> promotions.tenant_id
        'customer_addresses'  => 'customer',   // -> customers.tenant_id
        // loyalty_transactions.branch_id is nullable (e.g. Customer::add
        // Points()/redeemPoints() don't set it — loyalty adjustments aren't
        // always branch-specific). The generic branch_id branch below does
        // a bare whereIn(branch_id, ...) with no NULL handling, so every
        // null-branch_id row would be permanently invisible to every
        // tenant-scoped query — the mirror-image of the ActivityLog bug
        // (there, null meant "over-visible"; here it means "invisible").
        // customer_id is NOT NULL and always resolves the real tenant, so
        // route through that instead of the branch_id column.
        'loyalty_transactions' => 'customer',
    ];

    /**
     * @param Builder<Model> $builder
     * @param Model $model
     */
    // app/Models/Scopes/TenantScope.php

    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (!$user) return;
        if ($user->is_super_admin) return;

        // Get tenant_id — withoutGlobalScopes to avoid infinite loop
        $tenantId = $user->ownedTenant?->id
            ?? $user->staff()->withoutGlobalScopes()->first()?->tenant_id;

        if (!$tenantId) return;

        $table   = $model->getTable();
        $columns = \Cache::remember(
            "columns.{$table}",
            3600,
            fn() =>
            Schema::getColumnListing($table)
        );

        // Table-specific special cases are checked BEFORE the generic
        // column-shape fallbacks below — a table can happen to have a
        // `branch_id` (or `tenant_id`) column that LOOKS like it fits the
        // generic case but isn't actually sufficient (nullable and
        // sometimes genuinely null, as with loyalty_transactions), so
        // explicit table knowledge always wins over guessing from columns.
        if ($table === 'activity_logs') {
            // Unlike roles.tenant_id, a NULL tenant_id here does NOT mean
            // "shared across every tenant" — it means the action had no
            // resolvable tenant context at all (a super-admin action, or a
            // console/job run with no authenticated user). Reusing the
            // generic nullable-tenant_id "OR NULL = shared" rule below
            // would leak every super-admin/system log entry to every
            // tenant. A plain equality check is correct here: a tenant only
            // ever sees rows that resolved to their own tenant_id.
            $builder->where("{$table}.tenant_id", $tenantId);
        } elseif ($table === 'categories') {
            // Categories use a pivot table instead of tenant_id column, and
            // are visible to a tenant when EITHER (a) explicitly shared with
            // them via category_tenant — their own custom categories — OR
            // (b) it's a super-admin-authored system category tagged with
            // their tenant's own business type (category_business_type),
            // e.g. a Coffee tenant sees "Beverages" but not a Mart-only
            // "Grocery" system category.
            $businessTypeId = Tenant::withoutGlobalScopes()->find($tenantId)?->business_type_id;

            $builder->where(function ($q) use ($tenantId, $businessTypeId) {
                $q->whereHas('tenants', fn($q2) => $q2->where('tenant_id', $tenantId));

                if ($businessTypeId) {
                    $q->orWhere(function ($q2) use ($businessTypeId) {
                        $q2->where('is_system', true)
                            ->whereHas('businessTypes', fn($q3) => $q3->where('business_types.id', $businessTypeId));
                    });
                }
            });
        } elseif (array_key_exists($table, self::INDIRECT_TENANT_RELATIONS)) {
            $relation = self::INDIRECT_TENANT_RELATIONS[$table];
            $builder->whereHas($relation, fn($q) => $q->where('tenant_id', $tenantId));
        } elseif ($table === 'refunds') {
            // refunds has neither tenant_id nor branch_id — it's tenant-owned
            // only indirectly via payment_id -> payments.branch_id. Model had
            // no #[ScopedBy] at all, and is reachable via a flat
            // Route::apiResource('refunds', ...)->only(['index','show']), so
            // any authenticated user with payments.manage in ANY tenant
            // could list/view every other tenant's refunds directly by id.
            $branchIds = Branch::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->pluck('id');
            $builder->whereHas('payment', fn($q) => $q->whereIn('branch_id', $branchIds));
        } elseif (in_array('tenant_id', $columns)) {
            // tenant_id is nullable on a few tables (e.g. `roles.is_system`
            // templates) to mean "shared across every tenant" — a bare
            // `where tenant_id = X` would silently hide those rows instead
            // of just filtering out other tenants' data.
            $builder->where(function ($q) use ($table, $tenantId) {
                $q->where("{$table}.tenant_id", $tenantId)
                    ->orWhereNull("{$table}.tenant_id");
            });
        } elseif (in_array('branch_id', $columns)) {
            $branchIds = Branch::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->pluck('id');
            $builder->whereIn("{$table}.branch_id", $branchIds);
        }
    }
}
