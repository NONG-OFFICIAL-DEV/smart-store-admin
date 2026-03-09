<?php

namespace App\Models\Scopes;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
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

        if (in_array('tenant_id', $columns)) {
            $builder->where("{$table}.tenant_id", $tenantId);
        } elseif (in_array('branch_id', $columns)) {
            $branchIds = Branch::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->pluck('id');
            $builder->whereIn("{$table}.branch_id", $branchIds);
        }
    }
}
