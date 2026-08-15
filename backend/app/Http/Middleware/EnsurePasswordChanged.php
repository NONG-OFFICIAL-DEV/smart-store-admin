<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    // ── Routes a user must still reach while must_change_password is true ─────
    // — the change-password endpoint itself, plus me/logout so the frontend
    // can still resolve the session and sign the user out. ('refresh' isn't
    // listed — it no longer runs through this middleware at all.)
    private const ALLOWED_PATHS = [
        'api/v1/auth/password',
        'api/me',
        'api/logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user && $user->must_change_password && !$request->is(...self::ALLOWED_PATHS)) {
            return response()->json([
                'status'  => 'password_change_required',
                'message' => 'You must change your temporary password before continuing.',
            ], 403);
        }

        return $next($request);
    }
}
