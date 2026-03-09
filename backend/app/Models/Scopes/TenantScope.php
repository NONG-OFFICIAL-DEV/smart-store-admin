<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * @param Builder<Model> $builder
     * @param Model $model
     */

    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (!$user) return;

        if ($user->is_super_admin) return;

        if ($user->ownedTenant) {
            $builder->where($model->getTable() . '.tenant_id', $user->ownedTenant->id);
            return;
        }

        $staff = $user->staff()->first();
        if ($staff) {
            $builder->where($model->getTable() . '.tenant_id', $staff->tenant_id);
        }
    }
}
