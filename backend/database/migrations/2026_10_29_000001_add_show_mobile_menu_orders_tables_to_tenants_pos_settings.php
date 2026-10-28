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
            'pay_later_enabled' => true,
            'show_mobile_menu_orders' => true,
            'mobile_menu_order_sound' => true,
            'show_mobile_menu_orders_tables' => true,
        ]);

        // Separate toggle from show_mobile_menu_orders — that one controls
        // the carousel on Food POS, this one controls the same carousel
        // component when it's shown on the Tables page instead. The sound
        // setting (mobile_menu_order_sound) stays shared across both.
        DB::statement("ALTER TABLE tenants ALTER COLUMN pos_settings SET DEFAULT '{$default}'::jsonb");

        DB::statement("
            UPDATE tenants
            SET pos_settings = COALESCE(pos_settings, '{}'::jsonb)
                || '{\"show_mobile_menu_orders_tables\": true}'::jsonb
            WHERE NOT jsonb_exists(COALESCE(pos_settings, '{}'::jsonb), 'show_mobile_menu_orders_tables')
        ");
    }

    public function down(): void
    {
        DB::statement("UPDATE tenants SET pos_settings = pos_settings - 'show_mobile_menu_orders_tables'");
    }
};
