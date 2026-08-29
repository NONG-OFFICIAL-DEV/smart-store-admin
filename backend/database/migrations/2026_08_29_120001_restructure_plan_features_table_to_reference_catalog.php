<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `plan_features` stops storing its own label text (`en`/`km`) — that now
 * lives once in `plan_feature_listings`, keyed by this row's `key`. Each
 * row keeps only its plan-specific `value`: a raw bool for boolean-type
 * catalog entries, or `{en, km}` for text-type (identical shape to what
 * this table's `en`/`km` columns already held, just moved into one JSON
 * column so a boolean-type value has somewhere to live too).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_features', function (Blueprint $table) {
            $table->json('value')->nullable()->after('key');
        });

        foreach (DB::table('plan_features')->get() as $feature) {
            DB::table('plan_features')->where('id', $feature->id)->update([
                'value' => json_encode(['en' => $feature->en, 'km' => $feature->km]),
            ]);
        }

        Schema::table('plan_features', function (Blueprint $table) {
            $table->dropColumn(['en', 'km']);
        });
    }

    public function down(): void
    {
        Schema::table('plan_features', function (Blueprint $table) {
            $table->string('en')->nullable();
            $table->string('km')->nullable();
        });

        foreach (DB::table('plan_features')->get() as $feature) {
            $value = json_decode($feature->value ?? '{}', true);

            DB::table('plan_features')->where('id', $feature->id)->update([
                'en' => $value['en'] ?? '',
                'km' => $value['km'] ?? null,
            ]);
        }

        Schema::table('plan_features', function (Blueprint $table) {
            $table->dropColumn('value');
        });
    }
};
