<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Without this, a subscription's status (suspended/cancelled) — and a
 * tenant's own is_active flag — change nothing today. Blocks a tenant's
 * write/operational routes once their trial has lapsed, their subscription
 * was cancelled, or a super admin suspended the tenant directly, so
 * "Suspend Tenant" in the admin UI actually means something instead of
 * only changing a badge color in the tenant list.
 *
 * Super admin always bypasses (matches every other gate in this app). A
 * tenant with no subscription at all is let through — that's a data gap,
 * not a billing violation, and blocking on missing data would lock out
 * every tenant created before this feature existed.
 *
 * Response uses ApiResponse::error() (not a raw response()->json()) so the
 * frontend can key a translated message off `code` instead of displaying
 * this English string verbatim — see api.js's SUBSCRIPTION_BLOCK_CODES.
 */
class EnsureSubscriptionActive
{
    use ApiResponse;

    private const BLOCKED_STATUSES = ['suspended', 'cancelled'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user || $user->is_super_admin) {
            return $next($request);
        }

        $tenantId = $user->ownedTenant?->id ?? $user->staff?->tenant_id;
        if (!$tenantId) {
            return $next($request);
        }

        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);

        if ($tenant && !$tenant->is_active) {
            return $this->error(
                'This account has been suspended. Please contact support.',
                403,
                code: 'TENANT_SUSPENDED',
            );
        }

        // Tenant::activeSubscription is scoped to status IN (active, trial) —
        // it goes null the instant a subscription becomes suspended/cancelled,
        // which is exactly the state this middleware needs to detect. Query
        // the tenant's latest subscription directly, unfiltered by status.
        $status = TenantSubscription::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->latest('created_at')
            ->first()
            ?->status;

        if (in_array($status, self::BLOCKED_STATUSES, true)) {
            return $this->error(
                'Your subscription is not active. Please renew your plan to continue.',
                403,
                code: 'SUBSCRIPTION_STATUS_BLOCKED',
            );
        }

        return $next($request);
    }
}
