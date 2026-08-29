<?php

use App\Enums\PlanFeatureValueType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Catalog of feature rows every Plan references by `key` — label text and
 * value type defined once here instead of retyped (translation included)
 * on every plan that has it. Seeds one catalog row per key currently
 * distinct across `plan_features`, labeled from that key's first-seen
 * en/km text, all as `text` type (safe default — none of today's rows are
 * a clean yes/no). Reused keys (e.g. "inventory", "qr_menu" already
 * shared by Pro/Enterprise) naturally collapse to a single catalog row as
 * a side effect of this being keyed by DISTINCT key. down() only drops
 * the table — this one-time transform isn't reversed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_feature_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('label_en');
            $table->string('label_km')->nullable();
            $table->string('value_type')->default(PlanFeatureValueType::Text->value);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        $seen = [];
        $sortOrder = 0;
        $now = now();

        foreach (DB::table('plan_features')->orderBy('created_at')->get() as $feature) {
            if (isset($seen[$feature->key])) {
                continue;
            }

            $seen[$feature->key] = true;

            DB::table('plan_feature_listings')->insert([
                'id' => (string) Str::uuid(),
                'key' => $feature->key,
                'label_en' => $feature->en,
                'label_km' => $feature->km,
                'value_type' => PlanFeatureValueType::Text->value,
                'sort_order' => $sortOrder++,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_feature_listings');
    }
};
