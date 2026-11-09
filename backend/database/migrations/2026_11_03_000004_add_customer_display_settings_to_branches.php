<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $default = json_encode([
            'enabled' => false,
            'promo_image_url' => null,
            'promo_title' => null,
            'promo_subtitle' => null,
            'wifi_text' => null,
            'thank_you_delay_seconds' => 10,
            'merchant_qr_image_url' => null,
        ]);

        Schema::table('branches', function (Blueprint $table) use ($default) {
            $table->jsonb('customer_display_settings')->default(DB::raw("'{$default}'::jsonb"));
        });

        // customer_display briefly lived on tenants.pos_settings (Phase 1/2
        // of the customer-display feature, never shipped a settings UI) —
        // it belongs per-branch instead (different branches want different
        // WiFi text/promos), so any tenant that already had one is
        // backfilled onto every one of its branches here rather than lost.
        // jsonb_exists(), not the `?` operator — PDO parses a bare `?` in a
        // raw DB::statement() as its own positional bind placeholder (see
        // the sibling tenants.pos_settings migration, same fix).
        DB::statement("
            UPDATE branches b
            SET customer_display_settings = t.pos_settings->'customer_display'
            FROM tenants t
            WHERE b.tenant_id = t.id
              AND jsonb_exists(t.pos_settings, 'customer_display')
        ");
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('customer_display_settings');
        });
    }
};
