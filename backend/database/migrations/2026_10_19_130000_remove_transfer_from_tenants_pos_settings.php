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
        ]);

        DB::statement("ALTER TABLE tenants ALTER COLUMN pos_settings SET DEFAULT '{$default}'::jsonb");

        // Drop 'transfer' from every tenant's payment_methods array, and
        // fall back their default to cash if it had been set to transfer.
        DB::statement("
            UPDATE tenants
            SET pos_settings = jsonb_set(
                pos_settings,
                '{payment_methods}',
                COALESCE(
                    (SELECT jsonb_agg(value) FROM jsonb_array_elements(pos_settings->'payment_methods') AS value WHERE value <> '\"transfer\"'::jsonb),
                    '[]'::jsonb
                )
            )
            WHERE jsonb_exists(COALESCE(pos_settings, '{}'::jsonb), 'payment_methods')
        ");

        DB::statement("
            UPDATE tenants
            SET pos_settings = jsonb_set(pos_settings, '{default_payment_method}', '\"cash\"'::jsonb)
            WHERE pos_settings->>'default_payment_method' = 'transfer'
        ");
    }

    public function down(): void
    {
        $default = json_encode([
            'order_types' => ['dine_in', 'takeaway', 'delivery'],
            'customer_selection' => true,
            'order_notes' => true,
            'payment_methods' => ['cash', 'card', 'qr', 'transfer', 'pay_later'],
            'default_payment_method' => 'cash',
        ]);

        DB::statement("ALTER TABLE tenants ALTER COLUMN pos_settings SET DEFAULT '{$default}'::jsonb");
    }
};
