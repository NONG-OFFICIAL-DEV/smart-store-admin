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

        if (!$user->hasPermission($permission)) {
            return response()->json([
                'message'  => 'Forbidden',
                'required' => $permission,
            ], 403);
        }

        return $next($request);
    }
}
