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
            'customer_display' => [
                'enabled' => false,
                'promo_image_url' => null,
                'promo_title' => null,
                'promo_subtitle' => null,
                'wifi_text' => null,
                'thank_you_delay_seconds' => 10,
                'merchant_qr_image_url' => null,
            ],
        ]);

        // No KHQR payload generation exists in this app (see
        // CustomerDisplayController's docblocks) — a tenant without one
        // uploads a static image of their own bank's merchant QR instead,
        // shown as-is with the amount rendered large alongside it.
        DB::statement("ALTER TABLE tenants ALTER COLUMN pos_settings SET DEFAULT '{$default}'::jsonb");

        DB::statement("
            UPDATE tenants
            SET pos_settings = jsonb_set(
                COALESCE(pos_settings, '{}'::jsonb),
                '{customer_display,merchant_qr_image_url}',
                'null'::jsonb,
                true
            )
            WHERE NOT jsonb_exists(COALESCE(pos_settings->'customer_display', '{}'::jsonb), 'merchant_qr_image_url')
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE tenants
            SET pos_settings = pos_settings #- '{customer_display,merchant_qr_image_url}'
        ");
    }
};
