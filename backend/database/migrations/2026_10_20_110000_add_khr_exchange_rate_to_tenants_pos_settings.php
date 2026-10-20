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
            'khr_exchange_rate' => 4100,
            'khr_rounding' => 100,
        ]);

        DB::statement("ALTER TABLE tenants ALTER COLUMN pos_settings SET DEFAULT '{$default}'::jsonb");

        // Backfill existing tenants that don't already have these keys —
        // leaves everything else in their pos_settings untouched.
        DB::statement("
            UPDATE tenants
            SET pos_settings = COALESCE(pos_settings, '{}'::jsonb) || jsonb_build_object(
                'khr_exchange_rate', 4100,
                'khr_rounding', 100
            )
            WHERE NOT jsonb_exists(COALESCE(pos_settings, '{}'::jsonb), 'khr_exchange_rate')
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
        ]);

        DB::statement("ALTER TABLE tenants ALTER COLUMN pos_settings SET DEFAULT '{$default}'::jsonb");
        DB::statement("UPDATE tenants SET pos_settings = pos_settings - 'khr_exchange_rate' - 'khr_rounding'");
    }
};
