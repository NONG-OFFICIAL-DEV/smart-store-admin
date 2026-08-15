<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TwoFactorAuthController extends Controller
{
    use ApiResponse;

    public function __construct(private TwoFactorAuthService $twoFactor)
    {
    }

    public function setup(Request $request): JsonResponse
    {
        $this->assertMayManageTwoFactor($request->user());

        return $this->success($this->twoFactor->generateSecret($request->user()));
    }

    public function confirm(Request $request): JsonResponse
    {
        $this->assertMayManageTwoFactor($request->user());

        $request->validate(['code' => 'required|string']);

        try {
            $recoveryCodes = $this->twoFactor->confirm($request->user(), $request->code);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(['recovery_codes' => $recoveryCodes], 'Two-factor authentication enabled.');
    }

    public function disable(Request $request): JsonResponse
    {
        $this->assertMayManageTwoFactor($request->user());

        $this->twoFactor->disable($request->user());

        return $this->success(null, 'Two-factor authentication disabled.');
    }

    // Any user *can* have 2FA enabled and must complete it at login (see
    // AuthController::login()) — only *enabling/disabling* it is scoped to
    // super-admins and tenant owners, matching this app's notion of
    // "administrator."
    private function assertMayManageTwoFactor($user): void
    {
        abort_unless($user->is_super_admin || $user->ownedTenant()->exists(), 403);
    }
}
