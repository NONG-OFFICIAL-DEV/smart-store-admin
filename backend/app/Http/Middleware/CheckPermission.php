<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Super admin manages the whole system — bypasses every permission check.
        if ($user->is_super_admin) {
            return $next($request);
        }

        // Tenant owner has full access within their own tenant.
        if ($user->isTenantOwnerCached()) {
            return $next($request);
        }

        // A route can list several permissions separated by commas (e.g.
        // 'orders.manage,kitchen.manage') when more than one role legitimately
        // needs it — any ONE of them is enough. Single-permission routes keep
        // working unchanged (array_filter/explode on a comma-less string just
        // returns the one value).
        $required = array_filter(array_map('trim', explode(',', $permission)));
        $hasAny = collect($required)->contains(fn ($p) => $user->hasPermission($p));

        if (!$hasAny) {
            return response()->json([
                'message'  => 'Forbidden',
                'required' => $permission,
            ], 403);
        }

        return $next($request);
    }
}
