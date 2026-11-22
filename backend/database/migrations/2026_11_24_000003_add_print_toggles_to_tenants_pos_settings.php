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
            'loyalty_points_per_order' => 1,
            'show_queue_number' => true,
            'show_cashier_name' => true,
            'show_order_type' => true,
            'print_customer_receipt' => true,
            'print_kitchen_ticket' => false,
        ]);

        // Which document(s) print after Food POS checkout. Customer receipt
        // defaults on (preserves today's behavior); kitchen ticket defaults
        // off (brand-new capability — existing tenants don't suddenly get
        // new paper output until they opt in).
        DB::statement("ALTER TABLE tenants ALTER COLUMN pos_settings SET DEFAULT '{$default}'::jsonb");

        DB::statement("
            UPDATE tenants
            SET pos_settings = COALESCE(pos_settings, '{}'::jsonb) || '{\"print_customer_receipt\": true, \"print_kitchen_ticket\": false}'::jsonb
            WHERE NOT jsonb_exists(COALESCE(pos_settings, '{}'::jsonb), 'print_customer_receipt')
        ");
    }

    public function down(): void
    {
        DB::statement("UPDATE tenants SET pos_settings = pos_settings - 'print_customer_receipt' - 'print_kitchen_ticket'");
    }
};
