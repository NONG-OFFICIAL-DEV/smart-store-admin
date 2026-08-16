<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `feature:CODE` — gates an action by whether the branch it applies to
 * actually supports that feature (branch_type_features), not by who's
 * asking. Unlike permission:X (a role capability — can this person manage
 * reservations at all), this is a structural constraint of the branch
 * itself — even the tenant Owner doesn't bypass it, since a popup kiosk
 * branch genuinely can't take reservations regardless of who's logged in.
 *
 * Resolves the branch two ways, in order:
 *   1. An already route-bound model with a branch_id column (edit/delete
 *      routes — {table}, {reservation}, etc.).
 *   2. `branch_id` in the request body (create routes — every Store*Request
 *      this is applied to already requires it directly).
 * If neither resolves a branch (or that branch has no branch_type_id set
 * yet — legacy data), the request is allowed through: fail-open on
 * ambiguity rather than blocking a request this middleware can't actually
 * evaluate.
 */
class EnsureBranchHasFeature
{
    public function handle(Request $request, Closure $next, string $featureCode): Response
    {
        $user = auth()->user();

        // Super admin bypasses everything system-wide, same as CheckPermission.
        if ($user?->is_super_admin) {
            return $next($request);
        }

        $branch = $this->resolveBranch($request);

        if ($branch?->branchType && ! $branch->branchType->hasFeature($featureCode)) {
            return response()->json([
                'message' => "This branch's type doesn't include this feature.",
                'feature' => $featureCode,
            ], 403);
        }

        return $next($request);
    }

    private function resolveBranch(Request $request): ?Branch
    {
        foreach ($request->route()?->parameters() ?? [] as $param) {
            if (is_object($param) && isset($param->branch_id)) {
                return Branch::withoutGlobalScopes()->with('branchType')->find($param->branch_id);
            }
        }

        if ($request->filled('branch_id')) {
            return Branch::withoutGlobalScopes()->with('branchType')->find($request->input('branch_id'));
        }

        return null;
    }
}
