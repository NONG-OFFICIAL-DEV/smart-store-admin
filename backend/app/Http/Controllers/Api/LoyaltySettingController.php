<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoyaltySetting;
use App\Models\Tenant;
use App\Models\TelegramSetting;
use App\Services\TenantResolver;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The tenant-facing counterpart of LoyaltyObserver's earning rule and
 * notification toggles — one row per tenant (see LoyaltySetting::forTenant()),
 * not a superadmin-only global setting like TelegramSetting/EmenuSetting.
 */
class LoyaltySettingController extends Controller
{
    use ApiResponse;

    public function __construct(private TenantResolver $tenantResolver)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $tenantId = $this->tenantResolver->resolve($request);
        $tenant = Tenant::findOrFail($tenantId);

        return $this->success($this->format(LoyaltySetting::forTenant($tenantId), $tenant));
    }

    public function update(Request $request): JsonResponse
    {
        $tenantId = $this->tenantResolver->resolve($request);
        $tenant = Tenant::findOrFail($tenantId);

        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'earning_type' => ['sometimes', 'in:per_order,per_currency'],
            'earning_rate' => ['sometimes', 'numeric', 'min:0'],
            'notify_stamps_earned' => ['sometimes', 'boolean'],
            'notify_reward_ready' => ['sometimes', 'boolean'],
            'notify_reward_expiring' => ['sometimes', 'boolean'],
            'notify_birthday' => ['sometimes', 'boolean'],
        ]);

        $settings = LoyaltySetting::forTenant($tenantId);
        $settings->update($data);

        return $this->success($this->format($settings->fresh(), $tenant), 'Loyalty settings updated.');
    }

    private function format(LoyaltySetting $settings, Tenant $tenant): array
    {
        return [
            'enabled' => $settings->enabled,
            'earning_type' => $settings->earning_type,
            'earning_rate' => (float) $settings->earning_rate,
            'notify_stamps_earned' => $settings->notify_stamps_earned,
            'notify_reward_ready' => $settings->notify_reward_ready,
            'notify_reward_expiring' => $settings->notify_reward_expiring,
            'notify_birthday' => $settings->notify_birthday,
            // The Mini App join deep-link — built here rather than in the
            // frontend so SettingsHub never needs the bot username (platform-
            // wide, superadmin-only config it has no route to read) or to
            // know the startapp= convention itself.
            'join_url' => $this->buildJoinUrl($tenant),
        ];
    }

    private function buildJoinUrl(Tenant $tenant): ?string
    {
        $username = TelegramSetting::username();

        if (! $username || ! $tenant->slug) {
            return null;
        }

        return "https://t.me/{$username}?startapp={$tenant->slug}";
    }
}
