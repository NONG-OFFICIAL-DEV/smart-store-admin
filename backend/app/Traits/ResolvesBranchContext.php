<?php

namespace App\Traits;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Mart POS/report endpoints assume the caller is Staff (a single fixed
 * branch) — a tenant OWNER has no Staff record at all, so
 * `auth()->user()->staff->branch_id` fatals for them. Resolves the tenant
 * id and an effective branch id for either caller shape: an owner with
 * exactly one branch gets it automatically, an owner with more than one
 * branch and no explicit branch_id is asked to pick one rather than
 * silently showing the wrong branch's data.
 */
trait ResolvesBranchContext
{
    protected function resolveTenantId(): ?string
    {
        $user = auth()->user();

        return $user->staff?->tenant_id ?? $user->ownedTenant?->id;
    }

    protected function resolveBranchId(Request $request): ?string
    {
        if ($request->branch_id) {
            return $request->branch_id;
        }

        $user = auth()->user();

        if ($user->staff) {
            return $user->staff->branch_id;
        }

        $tenant = $user->ownedTenant;
        $branchIds = $tenant ? Branch::where('tenant_id', $tenant->id)->pluck('id') : collect();

        if ($branchIds->count() > 1) {
            throw ValidationException::withMessages([
                'branch_id' => 'This tenant has multiple branches — please select one.',
            ]);
        }

        return $branchIds->first();
    }
}
