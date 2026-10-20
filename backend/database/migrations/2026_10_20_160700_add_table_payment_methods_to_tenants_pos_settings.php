<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $default = json_encode([
            'order_types' => ['dine_in', 'takeaway', 'delivery'],
            'customer_selection' => true,
            'order_notes' => true,
            'payment_methods' => ['cash', 'card', 'qr', 'pay_later'],
            'default_payment_method' => 'cash',
            'table_payment_methods' => ['cash', 'card'],
            'default_table_payment_method' => 'cash',
            'khr_exchange_rate' => 4100,
            'khr_rounding' => 100,
        ]);

        DB::statement("ALTER TABLE tenants ALTER COLUMN pos_settings SET DEFAULT '{$default}'::jsonb");

        // Backfill existing tenants that don't already have these keys —
        // leaves everything else in their pos_settings untouched.
        DB::statement("
            UPDATE tenants
            SET pos_settings = COALESCE(pos_settings, '{}'::jsonb) || jsonb_build_object(
                'table_payment_methods', jsonb_build_array('cash', 'card'),
                'default_table_payment_method', 'cash'
            )
            WHERE NOT jsonb_exists(COALESCE(pos_settings, '{}'::jsonb), 'table_payment_methods')
        ");
    }

    public function down(): void
    {
        $default = json_encode([
            'order_types' => ['dine_in', 'takeaway', 'delivery'],
            'customer_selection' => true,
            'order_notes' => true,
            'payment_methods' => ['cash', 'card', 'qr', 'pay_later'],
            'default_payment_method' => 'cash',
            'khr_exchange_rate' => 4100,
            'khr_rounding' => 100,
        ]);

        DB::statement("ALTER TABLE tenants ALTER COLUMN pos_settings SET DEFAULT '{$default}'::jsonb");
        DB::statement("UPDATE tenants SET pos_settings = pos_settings - 'table_payment_methods' - 'default_table_payment_method'");
    }
};
