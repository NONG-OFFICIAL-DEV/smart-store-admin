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

        // customer_display moved to branches.customer_display_settings (see
        // 2026_11_03_000004) — the two prior migrations that added it here
        // (…000002, …000003) baked it into the COLUMN-LEVEL default via raw
        // ALTER COLUMN SET DEFAULT, which Tenant::DEFAULT_POS_SETTINGS no
        // longer matches; a brand new tenant row still picked it up from
        // Postgres regardless of the PHP constant. Realigns the two.
        DB::statement("ALTER TABLE tenants ALTER COLUMN pos_settings SET DEFAULT '{$default}'::jsonb");

        DB::statement("UPDATE tenants SET pos_settings = pos_settings - 'customer_display'");
    }

    public function down(): void
    {
        // Not reconstructable (000002/000003's exact prior default) —
        // customer_display's real home is branches.customer_display_settings
        // now, not worth restoring here.
    }
};
