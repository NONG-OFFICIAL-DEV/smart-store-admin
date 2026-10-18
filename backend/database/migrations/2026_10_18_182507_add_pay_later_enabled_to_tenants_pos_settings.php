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
            'pay_later_enabled' => true,
        ]);

        // Whether the "Pay Later" checkout option shows on the POS screen —
        // same DB-level default pattern as the original pos_settings column
        // so every future insert (new tenants, tests, seeders) gets it
        // without relying solely on the PHP constant
        // (Tenant::DEFAULT_POS_SETTINGS). Raw SQL — this project has no
        // doctrine/dbal installed, so Blueprint::change() isn't available.
        DB::statement("ALTER TABLE tenants ALTER COLUMN pos_settings SET DEFAULT '{$default}'::jsonb");

        // Merge the new key into every existing tenant's settings without
        // touching whatever they've already customized for the other keys.
        // jsonb_exists(), not the `?` operator — PDO treats a bare `?` in a
        // raw statement as a bind placeholder regardless of surrounding SQL.
        DB::statement("
            UPDATE tenants
            SET pos_settings = COALESCE(pos_settings, '{}'::jsonb) || '{\"pay_later_enabled\": true}'::jsonb
            WHERE NOT jsonb_exists(COALESCE(pos_settings, '{}'::jsonb), 'pay_later_enabled')
        ");
    }

    public function down(): void
    {
        DB::statement("UPDATE tenants SET pos_settings = pos_settings - 'pay_later_enabled'");
    }
};
