<?php

namespace Tests\Feature\Loyalty;

use App\Http\Controllers\Api\CoffeePOSorderController;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\LoyaltyReward;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TelegramSetting;
use App\Models\User;
use App\Services\LoyaltyRedemptionService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * HTTP-level coverage of the public loyalty-card Mini App API — unlike
 * most Feature tests in this app, this one genuinely needs to go through
 * real HTTP (not a direct Service call), since the X-Telegram-Init-Data
 * header is the actual mechanism under test.
 */
class LoyaltyMiniAppControllerTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = 'test-bot-token';

    private function makeTenant(string $slug): Tenant
    {
        $owner = User::create([
            'email' => "{$slug}@example.test",
            'first_name' => 'Owner',
            'last_name' => 'User',
        ]);

        return Tenant::create([
            'name' => 'Better B Cafe',
            'slug' => $slug,
            'owner_user_id' => $owner->id,
        ]);
    }

    private function initDataFor(int $telegramUserId, string $firstName = 'Alex'): string
    {
        $params = [
            'auth_date' => (string) time(),
            'user' => json_encode(['id' => $telegramUserId, 'first_name' => $firstName]),
        ];

        ksort($params);
        $dataCheckString = collect($params)->map(fn ($v, $k) => "{$k}={$v}")->implode("\n");
        $secretKey = hash_hmac('sha256', self::BOT_TOKEN, 'WebAppData', true);
        $params['hash'] = hash_hmac('sha256', $dataCheckString, $secretKey);

        return http_build_query($params);
    }

    protected function setUp(): void
    {
        parent::setUp();
        TelegramSetting::current()->update(['bot_token' => self::BOT_TOKEN, 'bot_username' => 'test_bot']);
    }

    private function makeBranch(Tenant $tenant): Branch
    {
        return Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
            'address_line1' => '123 Main St',
            'city' => 'Phnom Penh',
        ]);
    }

    private function makeProduct(string $tenantId): \App\Models\Product
    {
        $category = Category::create(['name' => 'Drinks']);

        return $this->app->make(ProductService::class)->create([
            'category_id' => $category->id,
            'name' => 'Iced Latte',
            'base_price' => 4.5,
        ], $tenantId);
    }

    public function test_me_reports_not_joined_and_creates_no_customer_before_joining(): void
    {
        $tenant = $this->makeTenant('better-b-cafe');

        $response = $this->getJson("/api/v1/public/loyalty/{$tenant->slug}/me", [
            'X-Telegram-Init-Data' => $this->initDataFor(111222333, 'Alex'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.joined', false);
        $response->assertJsonPath('data.telegram.first_name', 'Alex');

        $this->assertSame(0, Customer::where('tenant_id', $tenant->id)->count());
    }

    public function test_join_creates_a_customer_and_links_the_telegram_identity(): void
    {
        $tenant = $this->makeTenant('better-b-cafe');

        $response = $this->postJson("/api/v1/public/loyalty/{$tenant->slug}/join", [
            'phone' => '012345678',
        ], [
            'X-Telegram-Init-Data' => $this->initDataFor(111222333, 'Alex'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.joined', true);
        $response->assertJsonPath('data.customer.first_name', 'Alex');
        $response->assertJsonPath('data.customer.loyalty_points', 0);

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $tenant->id,
            'telegram_chat_id' => '111222333',
            'phone' => '012345678',
        ]);
    }

    public function test_join_links_an_existing_customer_matched_by_phone_instead_of_duplicating(): void
    {
        $tenant = $this->makeTenant('better-b-cafe');
        $existing = Customer::create(['tenant_id' => $tenant->id, 'first_name' => 'Sok', 'phone' => '012345678']);

        $this->postJson("/api/v1/public/loyalty/{$tenant->slug}/join", [
            'phone' => '012345678',
        ], [
            'X-Telegram-Init-Data' => $this->initDataFor(111222333, 'Alex'),
        ])->assertOk();

        $this->assertSame(1, Customer::where('tenant_id', $tenant->id)->count());
        $this->assertSame('111222333', $existing->refresh()->telegram_chat_id);
        $this->assertSame('Sok', $existing->first_name);
    }

    public function test_me_reuses_the_same_customer_on_a_later_visit(): void
    {
        $tenant = $this->makeTenant('better-b-cafe');

        $this->postJson("/api/v1/public/loyalty/{$tenant->slug}/join", ['phone' => '012345678'], [
            'X-Telegram-Init-Data' => $this->initDataFor(111222333),
        ])->assertOk();

        $this->assertSame(1, Customer::where('tenant_id', $tenant->id)->count());

        $response = $this->getJson("/api/v1/public/loyalty/{$tenant->slug}/me", [
            'X-Telegram-Init-Data' => $this->initDataFor(111222333),
        ]);
        $response->assertOk();
        $response->assertJsonPath('data.joined', true);

        $this->assertSame(1, Customer::where('tenant_id', $tenant->id)->count());
    }

    public function test_rewards_endpoint_409s_before_joining(): void
    {
        $tenant = $this->makeTenant('better-b-cafe');

        $this->getJson("/api/v1/public/loyalty/{$tenant->slug}/rewards", [
            'X-Telegram-Init-Data' => $this->initDataFor(111222333),
        ])->assertStatus(409);
    }

    public function test_card_token_resolves_back_to_the_joined_customer(): void
    {
        $tenant = $this->makeTenant('better-b-cafe');
        $headers = ['X-Telegram-Init-Data' => $this->initDataFor(111222333)];

        $this->postJson("/api/v1/public/loyalty/{$tenant->slug}/join", ['phone' => '012345678'], $headers)->assertOk();
        $customer = Customer::where('telegram_chat_id', '111222333')->firstOrFail();

        $response = $this->getJson("/api/v1/public/loyalty/{$tenant->slug}/card-token", $headers);
        $response->assertOk();

        $token = $response->json('data.token');
        $this->assertSame($customer->id, $this->app->make(\App\Services\LoyaltyCardTokenService::class)->resolve($token));
    }

    public function test_an_invalid_init_data_is_rejected(): void
    {
        $tenant = $this->makeTenant('better-b-cafe');

        $this->getJson("/api/v1/public/loyalty/{$tenant->slug}/me", [
            'X-Telegram-Init-Data' => 'auth_date=1&user=%7B%22id%22%3A1%7D&hash=deadbeef',
        ])->assertStatus(401);
    }

    public function test_an_unknown_tenant_slug_404s(): void
    {
        $this->getJson('/api/v1/public/loyalty/does-not-exist/me', [
            'X-Telegram-Init-Data' => $this->initDataFor(1),
        ])->assertStatus(404);
    }

    public function test_full_redeem_flow_through_the_public_endpoints(): void
    {
        $tenant = $this->makeTenant('better-b-cafe');
        $reward = LoyaltyReward::create([
            'tenant_id' => $tenant->id,
            'name' => 'Free Iced Matcha Latte',
            'points_cost' => 10,
            'is_active' => true,
        ]);

        $headers = ['X-Telegram-Init-Data' => $this->initDataFor(555666777)];

        $this->postJson("/api/v1/public/loyalty/{$tenant->slug}/join", ['phone' => '099887766'], $headers)->assertOk();
        $customer = Customer::where('telegram_chat_id', '555666777')->firstOrFail();
        $customer->addPoints(10, null, 'earn');

        $rewardsResponse = $this->getJson("/api/v1/public/loyalty/{$tenant->slug}/rewards", $headers);
        $rewardsResponse->assertOk();
        $rewardsResponse->assertJsonPath('data.0.can_redeem', true);

        $redeemResponse = $this->postJson("/api/v1/public/loyalty/{$tenant->slug}/redeem", [
            'reward_id' => $reward->id,
        ], $headers);
        $redeemResponse->assertCreated();
        $redeemResponse->assertJsonPath('data.status', 'pending');
        $code = $redeemResponse->json('data.code');

        $this->assertSame(0, $customer->refresh()->loyalty_points);

        $statusResponse = $this->getJson("/api/v1/public/loyalty/{$tenant->slug}/redeem/{$code}", $headers);
        $statusResponse->assertOk();
        $statusResponse->assertJsonPath('data.status', 'pending');

        $cancelResponse = $this->postJson("/api/v1/public/loyalty/{$tenant->slug}/redeem/{$code}/cancel", [], $headers);
        $cancelResponse->assertOk();
        $cancelResponse->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(10, $customer->refresh()->loyalty_points);
    }

    public function test_rewards_endpoint_exposes_discount_type_validity_and_branch_scope(): void
    {
        $tenant = $this->makeTenant('better-b-cafe');
        $branch = $this->makeBranch($tenant);

        $validUntil = now()->addMonths(2)->toDateString();

        LoyaltyReward::create([
            'tenant_id' => $tenant->id,
            'name' => '2,000 Riel Discount',
            'name_km' => 'បញ្ចុះតម្លៃ ២,០០០ ៛',
            'points_cost' => 5,
            'type' => 'discount_amount',
            'discount_value' => 2000,
            'valid_until' => $validUntil,
            'is_active' => true,
        ])->branches()->sync([$branch->id]);

        $headers = ['X-Telegram-Init-Data' => $this->initDataFor(111222333)];
        $this->postJson("/api/v1/public/loyalty/{$tenant->slug}/join", ['phone' => '012345678'], $headers)->assertOk();

        $response = $this->getJson("/api/v1/public/loyalty/{$tenant->slug}/rewards", $headers);
        $response->assertOk();

        $row = $response->json('data.0');
        $this->assertSame('បញ្ចុះតម្លៃ ២,០០០ ៛', $row['name_km']);
        $this->assertSame('discount_amount', $row['type']);
        $this->assertEquals(2000.0, $row['discount_value']);
        $this->assertNotNull($row['valid_until']);
        $this->assertSame(['Main Branch'], $row['branch_names']);
    }

    public function test_rewards_endpoint_reports_all_branches_as_null_scope(): void
    {
        $tenant = $this->makeTenant('better-b-cafe');

        LoyaltyReward::create([
            'tenant_id' => $tenant->id,
            'name' => 'Free Fresh Croissant',
            'points_cost' => 7,
            'type' => 'free_item',
            'is_active' => true,
        ]);

        $headers = ['X-Telegram-Init-Data' => $this->initDataFor(111222333)];
        $this->postJson("/api/v1/public/loyalty/{$tenant->slug}/join", ['phone' => '012345678'], $headers)->assertOk();

        $response = $this->getJson("/api/v1/public/loyalty/{$tenant->slug}/rewards", $headers);
        $response->assertOk();

        $this->assertNull($response->json('data.0.branch_names'));
    }

    public function test_history_enriches_an_earn_row_with_the_order_branch_and_items(): void
    {
        $tenant = $this->makeTenant('better-b-cafe');
        $owner = $tenant->owner;
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);

        $headers = ['X-Telegram-Init-Data' => $this->initDataFor(111222333)];
        $this->postJson("/api/v1/public/loyalty/{$tenant->slug}/join", ['phone' => '012345678'], $headers)->assertOk();
        $customer = Customer::where('telegram_chat_id', '111222333')->firstOrFail();

        $this->app->make(CoffeePOSorderController::class)->coffeeOrders(Request::create('/coffee/pos/orders', 'POST', [
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]));

        $response = $this->getJson("/api/v1/public/loyalty/{$tenant->slug}/history", $headers);
        $response->assertOk();

        $row = $response->json('data.0');
        $this->assertSame('earn', $row['type']);
        $this->assertSame('Main Branch', $row['branch_name']);
        $this->assertNotNull($row['order_number']);
        $this->assertEquals(4.5, $row['order_total']);
        $this->assertSame('Iced Latte', $row['order_items_summary']);

        $this->assertSame(1, $response->json('meta.summary.visits'));
        $this->assertSame(1, $response->json('meta.summary.stamps'));
        $this->assertSame(0, $response->json('meta.summary.rewards'));
    }

    public function test_history_enriches_a_redeem_row_via_the_confirmed_redemptions_own_branch_and_reward(): void
    {
        $tenant = $this->makeTenant('better-b-cafe');
        $owner = $tenant->owner;
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);

        $reward = LoyaltyReward::create([
            'tenant_id' => $tenant->id,
            'name' => 'Free Iced Matcha Latte',
            'points_cost' => 10,
            'is_active' => true,
        ]);

        $headers = ['X-Telegram-Init-Data' => $this->initDataFor(555666777)];
        $this->postJson("/api/v1/public/loyalty/{$tenant->slug}/join", ['phone' => '099887766'], $headers)->assertOk();
        $customer = Customer::where('telegram_chat_id', '555666777')->firstOrFail();
        $customer->addPoints(10, null, 'earn');

        $service = $this->app->make(LoyaltyRedemptionService::class);
        $redemption = $service->requestRedemption($customer, $reward);
        $service->confirm($redemption, $owner, null, $branch->id);

        $response = $this->getJson("/api/v1/public/loyalty/{$tenant->slug}/history", $headers);
        $response->assertOk();

        $redeemRow = collect($response->json('data'))->firstWhere('type', 'redeem');
        $this->assertSame('Main Branch', $redeemRow['branch_name']);
        $this->assertSame('Free Iced Matcha Latte', $redeemRow['reward_name']);

        $this->assertSame(1, $response->json('meta.summary.visits'));
        $this->assertSame(10, $response->json('meta.summary.stamps'));
        $this->assertSame(1, $response->json('meta.summary.rewards'));
    }
}
