<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Each prior pos_settings migration independently re-declared the
        // full column DEFAULT and a couple of them (pay_later_enabled,
        // table_payment_methods) dropped keys a previous migration had
        // added — the DEFAULT drifted out of sync with
        // Tenant::DEFAULT_POS_SETTINGS. Restoring the complete set here
        // (not just the two new keys) so a newly-created tenant's
        // DB-derived pos_settings matches the PHP constant again.
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
        ]);

        // Whether the Food POS screen shows the mobile e-menu orders
        // carousel, and whether a sound plays when a new one comes in —
        // same DB-level default pattern as pay_later_enabled so every future
        // insert gets both without relying solely on the PHP constant.
        DB::statement("ALTER TABLE tenants ALTER COLUMN pos_settings SET DEFAULT '{$default}'::jsonb");

        // Merge the new keys into every existing tenant's settings without
        // touching whatever they've already customized for the other keys.
        // Also defensively backfills pay_later_enabled for any tenant
        // created while an intervening migration's DEFAULT had dropped it.
        DB::statement("
            UPDATE tenants
            SET pos_settings = COALESCE(pos_settings, '{}'::jsonb)
                || '{\"show_mobile_menu_orders\": true, \"mobile_menu_order_sound\": true}'::jsonb
            WHERE NOT jsonb_exists(COALESCE(pos_settings, '{}'::jsonb), 'show_mobile_menu_orders')
                OR NOT jsonb_exists(COALESCE(pos_settings, '{}'::jsonb), 'mobile_menu_order_sound')
        ");
        DB::statement("
            UPDATE tenants
            SET pos_settings = COALESCE(pos_settings, '{}'::jsonb) || '{\"pay_later_enabled\": true}'::jsonb
            WHERE NOT jsonb_exists(COALESCE(pos_settings, '{}'::jsonb), 'pay_later_enabled')
        ");
    }

    public function down(): void
    {
        DB::statement("UPDATE tenants SET pos_settings = pos_settings - 'show_mobile_menu_orders' - 'mobile_menu_order_sound'");
    }
};
