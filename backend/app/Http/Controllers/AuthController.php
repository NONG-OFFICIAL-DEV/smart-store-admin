<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    // Login user
    public function login(Request $request)
    {
        // ── Validate input ────────────────────────────────────────────────────
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // ── Check user exists first (to give specific error messages) ─────────
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status'  => 'invalid_credentials',
                'message' => 'Email or password is incorrect',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'status'  => 'account_inactive',
                'message' => 'Your account has been deactivated. Please contact support.',
            ], 403);
        }

        // ── Attempt JWT login ─────────────────────────────────────────────────
        // NOTE: JWTAuth::attempt() calls getAuthPassword() which returns password_hash
        // so it correctly checks against our password_hash column
        try {
            $credentials = [
                'email'    => $request->email,
                'password' => $request->password,
            ];

            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json([
                    'status'  => 'invalid_credentials',
                    'message' => 'Email or password is incorrect',
                ], 401);
            }

            // ── Update last_login_at ──────────────────────────────────────────
            $user->update([
                'last_login_at' => now(),
            ]);
            $user = $user->fresh(); // ← reload from DB so last_login_at is no longer null


            // ── Load relationships ────────────────────────────────────────────
            $user->load([
                'staffProfiles.role.permissions',
                'staffProfiles.branch',
            ]);

            return response()->json([
                'status'     => 'success',
                'token'      => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user'       => [
                    'id'            => $user->id,
                    'email'         => $user->email,
                    'phone'         => $user->phone,
                    'first_name'    => $user->first_name,
                    'last_name'     => $user->last_name,
                    'full_name'     => $user->full_name,
                    'avatar_url'    => $user->avatar_url,
                    'is_active'     => $user->is_active,
                    'last_login_at' => $user->last_login_at,  // ← now updated
                    'staff'         => $user->staffProfiles,  // roles + branches
                ],
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Could not create token, please try again',
            ], 500);
        }
    }


    // Get currently authenticated user
    public function me()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            // $unreadCount = $user->notifications()->where('is_read', false)->count();
            return response()->json([
                'user' => $user,
                // 'unread_notifications_count' => $unreadCount,
            ]);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Token invalid or expired'], 401);
        }
    }

    // Logout
    public function logout()
    {
        try {
            JWTAuth::parseToken()->invalidate();
            return response()->json(['message' => 'Logged out successfully']);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Failed to logout, token invalid'], 500);
        }
    }
}
