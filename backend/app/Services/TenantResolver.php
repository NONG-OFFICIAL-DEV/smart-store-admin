<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantResolver
{
    /**
     * Resolves the active tenant for the currently authenticated user.
     *
     * In a multi-tenant system, every write operation (creating staff,
     * branches, menus, etc.) must be scoped to a tenant. This service
     * centralises that resolution logic so no controller needs to
     * re-implement it.
     *
     * Resolution order:
     *   1. Super admin   → tenant_id must be provided explicitly in the request(admin can select tenant)
     *   2. Tenant owner  → resolved automatically from tenants.owner_user_id(tenant is auto asign owner_user_id it own)
     *   3. Tenant staff  → resolved automatically from the user's staff record
     * */

    public function resolve(Request $request): string
    {
        $tenantId = $this->resolveOrNull($request);

        return $tenantId ?? abort(403, 'Could not resolve tenant.');
    }

    public function resolveOrNull(Request $request): ?string
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        // Case 1: Super admin — must pass tenant_id explicitly
        if ($user->is_super_admin) {
            $request->validate([
                'tenant_id' => 'required|uuid|exists:tenants,id',
            ]);
            return $request->tenant_id;
        }

        // Case 2: Tenant owner
        $tenantId = Tenant::where('owner_user_id', $user->id)->value('id');
        if ($tenantId) {
            return $tenantId;
        }

        // Case 3: Tenant admin / staff
        return $user->staff?->tenant_id;
    }
}
