<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchaseStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('purchase_statuses')->insert([
            [
                'code' => 'draft',
                'label' => 'Draft',
                'is_final' => false,
                'affects_stock' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'request',
                'label' => 'Request',
                'is_final' => false,
                'affects_stock' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'approved',
                'label' => 'Approved',
                'is_final' => false,
                'affects_stock' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'ordered',
                'label' => 'Ordered',
                'is_final' => false,
                'affects_stock' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'pending',
                'label' => 'Pending',
                'is_final' => false,
                'affects_stock' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'received',
                'label' => 'Received',
                'is_final' => false,
                'affects_stock' => true, // 🔥 stock updated here
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'completed',
                'label' => 'Completed',
                'is_final' => true,
                'affects_stock' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'return',
                'label' => 'Return',
                'is_final' => true,
                'affects_stock' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'rejected',
                'label' => 'Rejected',
                'is_final' => true,  // final status
                'affects_stock' => false, // stock not affected
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
