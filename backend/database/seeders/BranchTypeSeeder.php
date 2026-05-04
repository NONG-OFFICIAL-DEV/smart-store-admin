<?php

namespace Database\Seeders;

use App\Models\BranchType;
use App\Models\BusinessType;
use Illuminate\Database\Seeder;

class BranchTypeSeeder extends Seeder
{
    public function run(): void
    {
        $branchTypes = [
            // ─── Restaurant ───────────────────────────────────────────
            'RESTAURANT' => [
                [
                    'code'      => 'RESTAURANT_HQ',
                    'name'      => 'Main Branch (HQ)',
                    'icon'      => '🏠',
                    'is_hq'     => true,
                    'is_active' => true,
                ],
                [
                    'code'      => 'RESTAURANT_DINE_IN',
                    'name'      => 'Dine-In Branch',
                    'icon'      => '🏙️',
                    'is_hq'     => false,
                    'is_active' => true,
                ],
                [
                    'code'      => 'RESTAURANT_DELIVERY',
                    'name'      => 'Delivery-Only (Ghost Kitchen)',
                    'icon'      => '🛵',
                    'is_hq'     => false,
                    'is_active' => true,
                ],
                [
                    'code'      => 'RESTAURANT_POPUP',
                    'name'      => 'Pop-up / Event Kiosk',
                    'icon'      => '🏕️',
                    'is_hq'     => false,
                    'is_active' => true,
                ],
            ],

            // ─── Coffee Shop ───────────────────────────────────────────
            'COFFEE_SHOP' => [
                [
                    'code'      => 'COFFEE_HQ',
                    'name'      => 'Main Branch (HQ)',
                    'icon'      => '🏠',
                    'is_hq'     => true,
                    'is_active' => true,
                ],
                [
                    'code'      => 'COFFEE_TAKEAWAY',
                    'name'      => 'Takeaway Branch',
                    'icon'      => '🛍️',
                    'is_hq'     => false,
                    'is_active' => true,
                ],
                [
                    'code'      => 'COFFEE_CAFE',
                    'name'      => 'Sit-down Café',
                    'icon'      => '📚',
                    'is_hq'     => false,
                    'is_active' => true,
                ],
                [
                    'code'      => 'COFFEE_KIOSK',
                    'name'      => 'Self-Service Kiosk',
                    'icon'      => '🤖',
                    'is_hq'     => false,
                    'is_active' => true,
                ],
            ],

            // ─── Mart ──────────────────────────────────────────────────
            'MART' => [
                [
                    'code'      => 'MART_HQ',
                    'name'      => 'Main Branch / Warehouse',
                    'icon'      => '🏠',
                    'is_hq'     => true,
                    'is_active' => true,
                ],
                [
                    'code'      => 'MART_RETAIL',
                    'name'      => 'Retail Store',
                    'icon'      => '🏬',
                    'is_hq'     => false,
                    'is_active' => true,
                ],
                [
                    'code'      => 'MART_MINI',
                    'name'      => 'Mini Mart / Satellite',
                    'icon'      => '📦',
                    'is_hq'     => false,
                    'is_active' => true,
                ],
                [
                    'code'      => 'MART_ONLINE',
                    'name'      => 'Online / E-Commerce',
                    'icon'      => '🌐',
                    'is_hq'     => false,
                    'is_active' => true,
                ],
            ],
        ];

        foreach ($branchTypes as $businessCode => $types) {
            $businessType = BusinessType::where('code', $businessCode)->first();

            if (! $businessType) {
                $this->command->warn("BusinessType [{$businessCode}] not found. Skipping.");
                continue;
            }

            foreach ($types as $i => $type) {
                BranchType::firstOrCreate(
                    [
                        'code' => $type['code'],
                    ],
                    [
                        'business_type_id' => $businessType->id,
                        'name'             => $type['name'],
                        'icon'             => $type['icon'],
                        'is_hq'            => $type['is_hq'] ?? false,
                        'is_active'        => $type['is_active'] ?? true,
                        'sort_order'       => $i + 1,
                    ]
                );
            }

            $this->command->info("✅ Branch types seeded for [{$businessCode}]");
        }
    }
}