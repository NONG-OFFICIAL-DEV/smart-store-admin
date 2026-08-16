<?php

namespace App\Http\Middleware;

use App\Models\TenantSubscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Without this, a subscription's status (suspended/cancelled) changes
 * nothing today — nothing else in the app branches on it. Blocks a tenant's
 * write/operational routes once their trial has lapsed or their
 * subscription was cancelled, so "suspended" actually means something.
 *
 * Super admin always bypasses (matches every other gate in this app). A
 * tenant with no subscription at all is let through — that's a data gap,
 * not a billing violation, and blocking on missing data would lock out
 * every tenant created before this feature existed.
 */
class EnsureSubscriptionActive
{
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
            return response()->json([
                'message' => 'Your subscription is not active. Please renew your plan to continue.',
                'subscription_status' => $status,
            ], 403);
        }

        return $next($request);
    }
}
