<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LoyaltyReward;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTransaction;
use App\Models\Tenant;
use App\Models\TelegramSetting;
use App\Services\LoyaltyCardTokenService;
use App\Services\LoyaltyRedemptionService;
use App\Services\LoyaltyRewardService;
use App\Services\TelegramInitDataValidator;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs the customer-facing Telegram Mini App ("loyalty-card" project) —
 * no staff JWT, identity comes entirely from Telegram's own signed
 * `initData` (see TelegramInitDataValidator). Mirrors DigitalMenuController's
 * convention for this app's other public, unauthenticated API group:
 * resolve everything explicitly by slug/id, since TenantScope is inert with
 * no authenticated user.
 *
 * A Telegram identity alone ("who you are") is not the same thing as "a
 * customer record this tenant has agreed to track points for" — that only
 * happens once someone taps through the Join screen and shares a phone
 * number (see join()). Every endpoint other than me()/join() requires an
 * already-linked customer and 409s otherwise, rather than silently
 * creating one.
 */
class LoyaltyMiniAppController extends Controller
{
    use ApiResponse;

    public function __construct(
        private TelegramInitDataValidator $initDataValidator,
        private LoyaltyRewardService $rewards,
        private LoyaltyRedemptionService $redemptions,
        private LoyaltyCardTokenService $cardTokens
    ) {
    }

    public function me(Request $request, string $tenantSlug): JsonResponse
    {
        [$tenant, $identity] = $this->authenticate($request, $tenantSlug);
        $customer = $this->findCustomer($tenant, $identity);

        if (! $customer) {
            return $this->success([
                'joined' => false,
                'tenant' => $this->formatTenant($tenant),
                'telegram' => [
                    'first_name' => $identity['first_name'],
                    'last_name' => $identity['last_name'],
                ],
            ]);
        }

        $settings = LoyaltySetting::forTenant($tenant->id);

        return $this->success([
            'joined' => true,
            'tenant' => $this->formatTenant($tenant),
            'customer' => $this->formatCustomer($customer),
            'earning_type' => $settings->earning_type,
            'earning_rate' => (float) $settings->earning_rate,
        ]);
    }

    /**
     * First-contact flow: share a phone number to either link this
     * Telegram identity to an existing customer (matched by phone within
     * this tenant) or create a new one. Safe to call again later — it's
     * idempotent for the same phone/telegram pairing.
     */
    public function join(Request $request, string $tenantSlug): JsonResponse
    {
        [$tenant, $identity] = $this->authenticate($request, $tenantSlug);

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
        ]);

        $customer = Customer::where('tenant_id', $tenant->id)
            ->where('phone', $validated['phone'])
            ->first();

        if ($customer) {
            $customer->update([
                'telegram_chat_id' => $identity['telegram_user_id'],
                'notify_telegram' => true,
            ]);
        } else {
            $customer = Customer::create([
                'tenant_id' => $tenant->id,
                'first_name' => ($validated['first_name'] ?? null) ?: ($identity['first_name'] ?: 'Telegram'),
                'last_name' => ($validated['last_name'] ?? null) ?: $identity['last_name'],
                'phone' => $validated['phone'],
                'source' => 'online',
                'telegram_chat_id' => $identity['telegram_user_id'],
                'notify_telegram' => true,
            ])->refresh(); // loyalty_points/total_spent/total_orders are
            // DB-level defaults, not mass-assignable — create()'s in-memory
            // instance won't reflect them without this.
        }

        $settings = LoyaltySetting::forTenant($tenant->id);

        return $this->success([
            'joined' => true,
            'tenant' => $this->formatTenant($tenant),
            'customer' => $this->formatCustomer($customer),
            'earning_type' => $settings->earning_type,
            'earning_rate' => (float) $settings->earning_rate,
        ]);
    }

    /**
     * A fresh rotating token for the Card screen's QR — the Mini App
     * re-polls this roughly every 60s while that screen is open so the
     * displayed code keeps rotating (see LoyaltyCardTokenService).
     */
    public function cardToken(Request $request, string $tenantSlug): JsonResponse
    {
        [, $customer] = $this->resolve($request, $tenantSlug);

        return $this->success([
            'token' => $this->cardTokens->generate($customer->id),
            'expires_in' => 60,
        ]);
    }

    public function rewards(Request $request, string $tenantSlug): JsonResponse
    {
        [, $customer] = $this->resolve($request, $tenantSlug);

        $available = $this->rewards->availableFor($customer->tenant_id, $request->query('branch_id'));

        return $this->success($available->map(fn (LoyaltyReward $reward) => [
            'id' => $reward->id,
            'name' => $reward->name,
            'name_km' => $reward->name_km,
            'description' => $reward->description,
            'description_km' => $reward->description_km,
            'image_url' => $reward->image_url,
            'points_cost' => $reward->points_cost,
            'can_redeem' => $customer->loyalty_points >= $reward->points_cost,
            'points_short' => max(0, $reward->points_cost - $customer->loyalty_points),
            'type' => $reward->type,
            'discount_value' => $reward->discount_value !== null ? (float) $reward->discount_value : null,
            'free_product_name' => $reward->freeProduct?->name,
            'valid_until' => $reward->valid_until,
            // Empty pivot = available at every branch — same convention
            // isAvailableAt() already uses, surfaced here so the customer
            // knows whether this reward is a this-location-only perk.
            'branch_names' => $reward->branches->isNotEmpty() ? $reward->branches->pluck('name')->values() : null,
        ])->values());
    }

    public function history(Request $request, string $tenantSlug): JsonResponse
    {
        [, $customer] = $this->resolve($request, $tenantSlug);

        $transactions = LoyaltyTransaction::where('customer_id', $customer->id)
            ->with(['order.branch', 'order.items', 'redemption.reward', 'redemption.branch', 'redemption.order.items'])
            ->latest('created_at')
            ->paginate((int) $request->query('perPage', 20));

        return $this->success(
            $transactions->map(fn (LoyaltyTransaction $t) => $this->formatTransaction($t))->values(),
            meta: [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'total' => $transactions->total(),
                'summary' => $this->historySummary($customer->id),
            ]
        );
    }

    // A 'redeem' row's order/branch always live on the LoyaltyRedemption
    // it created, not on the transaction itself — see
    // LoyaltyTransaction::redemption()'s doc comment.
    private function formatTransaction(LoyaltyTransaction $t): array
    {
        $order = $t->type === 'redeem' ? $t->redemption?->order : $t->order;
        $branch = $t->type === 'redeem' ? $t->redemption?->branch : $t->order?->branch;

        return [
            'id' => $t->id,
            'type' => $t->type,
            'points' => $t->points,
            'balance_after' => $t->balance_after,
            'description' => $t->description,
            'created_at' => $t->created_at,
            'branch_name' => $branch?->name,
            'order_number' => $order?->order_number,
            'order_total' => $order ? (float) $order->total_amount : null,
            'order_items_summary' => $order
                ? $order->items->pluck('product_name')->filter()->implode(' + ')
                : null,
            'reward_name' => $t->redemption?->reward?->name,
            'reward_name_km' => $t->redemption?->reward?->name_km,
        ];
    }

    // Lifetime, not scoped to the current page — a glanceable "member
    // since the start" stat strip, shown once at the top of the History
    // screen regardless of which page of transactions is loaded.
    private function historySummary(string $customerId): array
    {
        $base = LoyaltyTransaction::where('customer_id', $customerId);

        return [
            'visits' => (clone $base)->whereIn('type', ['earn', 'bonus'])->count(),
            'stamps' => (int) (clone $base)->whereIn('type', ['earn', 'bonus'])->sum('points'),
            'rewards' => (clone $base)->where('type', 'redeem')->count(),
        ];
    }

    public function redeem(Request $request, string $tenantSlug): JsonResponse
    {
        [$tenant, $customer] = $this->resolve($request, $tenantSlug);

        $request->validate(['reward_id' => ['required', 'uuid']]);

        $reward = LoyaltyReward::where('tenant_id', $tenant->id)->findOrFail($request->input('reward_id'));

        try {
            $redemption = $this->redemptions->requestRedemption($customer, $reward);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422, [], 'REDEMPTION_FAILED');
        }

        return $this->created($this->formatRedemption($redemption->load('reward.branches')));
    }

    public function redemptionStatus(Request $request, string $tenantSlug, string $code): JsonResponse
    {
        $this->resolve($request, $tenantSlug);

        $redemption = $this->redemptions->findByCode($code);

        if (! $redemption || $redemption->tenant_id !== $this->tenantId($tenantSlug)) {
            return $this->error('Redemption not found.', 404, [], 'NOT_FOUND');
        }

        return $this->success($this->formatRedemption($redemption));
    }

    public function cancelRedemption(Request $request, string $tenantSlug, string $code): JsonResponse
    {
        $this->resolve($request, $tenantSlug);

        $redemption = $this->redemptions->findByCode($code);

        if (! $redemption || $redemption->tenant_id !== $this->tenantId($tenantSlug)) {
            return $this->error('Redemption not found.', 404, [], 'NOT_FOUND');
        }

        $redemption = $this->redemptions->cancel($redemption);

        return $this->success($this->formatRedemption($redemption));
    }

    private function formatTenant(Tenant $tenant): array
    {
        return [
            'name' => $tenant->name,
            'logo_url' => $tenant->logo_url,
            'currency' => $tenant->currency ?? 'USD',
        ];
    }

    private function formatCustomer(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'phone' => $customer->phone,
            'loyalty_points' => $customer->loyalty_points,
            'member_since' => $customer->created_at,
        ];
    }

    private function formatRedemption($redemption): array
    {
        return [
            'code' => $redemption->code,
            'status' => $redemption->status,
            'expires_at' => $redemption->expires_at,
            'reward' => [
                'id' => $redemption->reward->id,
                'name' => $redemption->reward->name,
                'name_km' => $redemption->reward->name_km,
                'image_url' => $redemption->reward->image_url,
                'points_cost' => $redemption->reward->points_cost,
                'branch_names' => $redemption->reward->relationLoaded('branches') && $redemption->reward->branches->isNotEmpty()
                    ? $redemption->reward->branches->pluck('name')->values()
                    : null,
            ],
        ];
    }

    private function tenantId(string $tenantSlug): ?string
    {
        return Tenant::where('slug', $tenantSlug)->value('id');
    }

    /**
     * Resolves tenant + customer, aborting if this Telegram identity
     * hasn't joined yet — every screen past Join/Card needs an actual
     * customer record to read a balance from.
     *
     * @return array{0: Tenant, 1: Customer}
     */
    private function resolve(Request $request, string $tenantSlug): array
    {
        [$tenant, $identity] = $this->authenticate($request, $tenantSlug);
        $customer = $this->findCustomer($tenant, $identity);

        abort_if(! $customer, 409, 'Please join the loyalty program first.');

        return [$tenant, $customer];
    }

    /**
     * @return array{0: Tenant, 1: array}
     */
    private function authenticate(Request $request, string $tenantSlug): array
    {
        $tenant = Tenant::where('slug', $tenantSlug)->where('is_active', true)->first();

        abort_if(! $tenant, 404, 'Business not found.');

        $botToken = TelegramSetting::token();
        $initData = $request->header('X-Telegram-Init-Data', $request->input('init_data'));

        abort_if(! $botToken || ! $initData, 401, 'Telegram authentication is not configured.');

        $identity = $this->initDataValidator->validate($initData, $botToken);

        abort_if(! $identity, 401, 'Invalid Telegram session.');

        return [$tenant, $identity];
    }

    private function findCustomer(Tenant $tenant, array $identity): ?Customer
    {
        return Customer::where('tenant_id', $tenant->id)
            ->where('telegram_chat_id', $identity['telegram_user_id'])
            ->first();
    }
}
