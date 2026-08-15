<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * ProductControllerV2::store()/update() have validated and attempted to
     * mass-assign these 4 fields since that controller was written, and
     * ProductFormPage.vue has real, live input widgets collecting all four
     * (cup sizes, temperature options, shelf life, supplier code) — but
     * none of them were ever in Product::$fillable NOR real columns on
     * this table. Silent, confirmed data loss: a user fills these in,
     * submits, sees a success message, and the values vanish (Eloquent's
     * $fillable whitelist drops unknown keys with no error at all).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'cup_sizes')) {
                $table->jsonb('cup_sizes')->nullable();
            }
            if (! Schema::hasColumn('products', 'temperature_options')) {
                $table->jsonb('temperature_options')->nullable();
            }
            if (! Schema::hasColumn('products', 'shelf_life_hours')) {
                $table->smallInteger('shelf_life_hours')->nullable();
            }
            if (! Schema::hasColumn('products', 'supplier_code')) {
                $table->string('supplier_code', 60)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['cup_sizes', 'temperature_options', 'shelf_life_hours', 'supplier_code']);
        });
    }
};
