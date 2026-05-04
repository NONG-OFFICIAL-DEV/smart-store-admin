<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\BusinessType;

// database/seeders/BusinessTypeSeeder.php
public function run(): void
{
    $types = [
        ['code' => 'RESTAURANT',  'name' => 'Restaurant',  'icon' => '🍽️', 'sort_order' => 1],
        ['code' => 'COFFEE_SHOP', 'name' => 'Coffee Shop', 'icon' => '☕',  'sort_order' => 2],
        ['code' => 'MART',        'name' => 'Mart',        'icon' => '🏪', 'sort_order' => 3],
    ];

    foreach ($types as $type) {
        BusinessType::firstOrCreate(['code' => $type['code']], $type);
    }
}