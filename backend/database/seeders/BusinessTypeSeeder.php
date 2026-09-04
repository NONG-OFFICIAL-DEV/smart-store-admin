<?php

namespace Database\Seeders;

use App\Models\BusinessType;
use Illuminate\Database\Seeder;

class BusinessTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code'       => 'RESTAURANT',
                'name'       => 'Restaurant',
                'icon'       => '🍽️',
                'category'   => 'food',
                'is_active'  => true,
                'sort_order' => 1,
            ],
            [
                'code'       => 'COFFEE_SHOP',
                'name'       => 'Coffee Shop',
                'icon'       => '☕',
                'category'   => 'food',
                'is_active'  => true,
                'sort_order' => 2,
            ],
            [
                'code'       => 'MART',
                'name'       => 'Mart',
                'icon'       => '🏪',
                'category'   => 'mart',
                'is_active'  => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($types as $type) {
            BusinessType::firstOrCreate(
                ['code' => $type['code']],
                [
                    'name'       => $type['name'],
                    'icon'       => $type['icon'],
                    'category'   => $type['category'],
                    'is_active'  => $type['is_active'],
                    'sort_order' => $type['sort_order'],
                ]
            );

            $this->command->info("✅ BusinessType seeded: [{$type['code']}] {$type['name']}");
        }

        $this->command->info('');
        $this->command->info('🎉 All ' . count($types) . ' business types seeded successfully.');
    }
}