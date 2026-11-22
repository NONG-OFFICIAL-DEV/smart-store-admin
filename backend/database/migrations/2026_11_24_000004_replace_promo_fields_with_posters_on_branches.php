<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $default = json_encode([
            'enabled' => false,
            'posters' => [],
            'wifi_text' => null,
            'thank_you_delay_seconds' => 10,
            'merchant_qr_image_url' => null,
        ]);

        DB::statement("ALTER TABLE branches ALTER COLUMN customer_display_settings SET DEFAULT '{$default}'::jsonb");

        // The CFD redesign dropped the single promo_image_url/promo_title/
        // promo_subtitle trio in favor of a rotating `posters` array of
        // plain image URLs (no title/subtitle — see the new PosterCarousel
        // component). Existing single promo images are preserved as the
        // first (only) poster rather than lost; title/subtitle are dropped
        // since the new design has nowhere to show them.
        DB::statement("
            UPDATE branches
            SET customer_display_settings = (customer_display_settings - 'promo_image_url' - 'promo_title' - 'promo_subtitle')
                || jsonb_build_object(
                    'posters',
                    CASE
                        WHEN customer_display_settings->>'promo_image_url' IS NOT NULL
                            THEN jsonb_build_array(customer_display_settings->>'promo_image_url')
                        ELSE '[]'::jsonb
                    END
                )
            WHERE customer_display_settings IS NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE branches
            SET customer_display_settings = (customer_display_settings - 'posters')
                || jsonb_build_object(
                    'promo_image_url',
                    CASE
                        WHEN jsonb_array_length(COALESCE(customer_display_settings->'posters', '[]'::jsonb)) > 0
                            THEN customer_display_settings->'posters'->0
                        ELSE NULL
                    END,
                    'promo_title', NULL,
                    'promo_subtitle', NULL
                )
            WHERE customer_display_settings IS NOT NULL
        ");
    }
};
