<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            // ─── POS & Ordering ────────────────────────────────────────
            [
                'code'        => 'POS',
                'name'        => 'Point of Sale',
                'icon'        => '🖥️',
                'description' => 'Core cashier and checkout system for processing transactions.',
                'is_active'   => true,
            ],
            [
                'code'        => 'MENU',
                'name'        => 'Menu Management',
                'icon'        => '📋',
                'description' => 'Create and manage product menus, categories, and pricing.',
                'is_active'   => true,
            ],
            [
                'code'        => 'ONLINE_STORE',
                'name'        => 'Online Store',
                'icon'        => '🌐',
                'description' => 'E-commerce storefront for online ordering and product listings.',
                'is_active'   => true,
            ],

            // ─── Restaurant / Kitchen ──────────────────────────────────
            [
                'code'        => 'KDS',
                'name'        => 'Kitchen Display System',
                'icon'        => '👨‍🍳',
                'description' => 'Real-time order display screen for kitchen staff to manage and fulfill orders.',
                'is_active'   => true,
            ],
            [
                'code'        => 'TABLE_MGMT',
                'name'        => 'Table Management',
                'icon'        => '🪑',
                'description' => 'Visual table layout, seat assignment, and occupancy tracking.',
                'is_active'   => true,
            ],
            [
                'code'        => 'RESERVATION',
                'name'        => 'Reservations',
                'icon'        => '📅',
                'description' => 'Accept and manage advance table bookings from customers.',
                'is_active'   => true,
            ],

            // ─── Delivery & Queue ──────────────────────────────────────
            [
                'code'        => 'DELIVERY',
                'name'        => 'Delivery Management',
                'icon'        => '🛵',
                'description' => 'Manage delivery orders, assign riders, and track delivery status.',
                'is_active'   => true,
            ],
            [
                'code'        => 'QUEUE',
                'name'        => 'Queue Display',
                'icon'        => '🔢',
                'description' => 'Customer-facing queue number display for order pickup management.',
                'is_active'   => true,
            ],

            // ─── Loyalty & Retention ───────────────────────────────────
            [
                'code'        => 'LOYALTY',
                'name'        => 'Loyalty Program',
                'icon'        => '⭐',
                'description' => 'Points-based rewards system to retain and incentivize repeat customers.',
                'is_active'   => true,
            ],
            [
                'code'        => 'STAMP_CARD',
                'name'        => 'Stamp Card',
                'icon'        => '🎫',
                'description' => 'Digital stamp card — collect stamps per purchase and redeem rewards.',
                'is_active'   => true,
            ],

            // ─── Inventory & Supply Chain ──────────────────────────────
            [
                'code'        => 'INVENTORY',
                'name'        => 'Inventory Management',
                'icon'        => '📦',
                'description' => 'Track stock levels, movements, and product availability in real time.',
                'is_active'   => true,
            ],
            [
                'code'        => 'BARCODE',
                'name'        => 'Barcode Scanner',
                'icon'        => '📡',
                'description' => 'Scan product barcodes for fast checkout and inventory lookup.',
                'is_active'   => true,
            ],
            [
                'code'        => 'SUPPLIER',
                'name'        => 'Supplier Management',
                'icon'        => '🏭',
                'description' => 'Manage supplier contacts, purchase orders, and restocking workflows.',
                'is_active'   => true,
            ],
            [
                'code'        => 'STOCK_ALERT',
                'name'        => 'Stock Alert',
                'icon'        => '🔔',
                'description' => 'Automatic low-stock notifications to prevent stockouts.',
                'is_active'   => true,
            ],

            // ─── Reporting ─────────────────────────────────────────────
            [
                'code'        => 'REPORT',
                'name'        => 'Reports & Analytics',
                'icon'        => '📊',
                'description' => 'Sales summaries, revenue trends, and operational insights dashboard.',
                'is_active'   => true,
            ],
        ];

        foreach ($features as $feature) {
            Feature::firstOrCreate(
                ['code' => $feature['code']],
                [
                    'name'        => $feature['name'],
                    'icon'        => $feature['icon'],
                    'description' => $feature['description'],
                    'is_active'   => $feature['is_active'],
                ]
            );

            $this->command->info("✅ Feature seeded: [{$feature['code']}] {$feature['name']}");
        }

        $this->command->info('');
        $this->command->info('🎉 All ' . count($features) . ' features seeded successfully.');
    }
}