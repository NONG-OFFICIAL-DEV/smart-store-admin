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
            'payment_methods' => ['cash', 'card', 'qr', 'transfer', 'pay_later'],
            'default_payment_method' => 'cash',
        ]);

        // Replaces the standalone pay_later_enabled toggle with one unified
        // list of every payment method POS checkout/Settle Payment can
        // accept, plus which one pre-selects — same DB-level default
        // pattern as the rest of pos_settings so new tenants/tests/seeders
        // get it without relying solely on Tenant::DEFAULT_POS_SETTINGS.
        DB::statement("ALTER TABLE tenants ALTER COLUMN pos_settings SET DEFAULT '{$default}'::jsonb");

        // Backfill existing tenants, preserving their pay_later_enabled
        // intent rather than silently re-enabling it: a tenant that had
        // explicitly turned it off keeps Pay Later excluded from their new
        // payment_methods list.
        DB::statement("
            UPDATE tenants
            SET pos_settings = COALESCE(pos_settings, '{}'::jsonb) || jsonb_build_object(
                'payment_methods',
                CASE
                    WHEN COALESCE(pos_settings->>'pay_later_enabled', 'true') = 'false'
                    THEN '[\"cash\",\"card\",\"qr\",\"transfer\"]'::jsonb
                    ELSE '[\"cash\",\"card\",\"qr\",\"transfer\",\"pay_later\"]'::jsonb
                END,
                'default_payment_method', 'cash'
            )
            WHERE NOT jsonb_exists(COALESCE(pos_settings, '{}'::jsonb), 'payment_methods')
        ");
    }

    public function down(): void
    {
        $default = json_encode([
            'order_types' => ['dine_in', 'takeaway', 'delivery'],
            'customer_selection' => true,
            'order_notes' => true,
            'pay_later_enabled' => true,
        ]);

        DB::statement("ALTER TABLE tenants ALTER COLUMN pos_settings SET DEFAULT '{$default}'::jsonb");
        DB::statement("UPDATE tenants SET pos_settings = pos_settings - 'payment_methods' - 'default_payment_method'");
    }
};
