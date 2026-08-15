<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();

            $table->string('unit_name', 50);              // can, pack, box, pallet
            $table->string('unit_label', 50)->nullable(); // Can, 6-Pack, Case of 24
            $table->decimal('qty_per_base', 12, 3);       // 1, 6, 24, 240
            $table->string('barcode', 60)->nullable()->unique();

            $table->decimal('retail_price', 12, 2);       // regular customer
            $table->decimal('wholesale_price', 12, 2)->nullable(); // bulk buyer
            $table->decimal('cost_price', 12, 2)->nullable();      // for margin tracking

            $table->boolean('is_base_unit')->default(false); // true = the "can"
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);

            $table->timestampsTz();

            $table->index(['product_id', 'is_active']);
            $table->index('barcode');
        });

        // ── Add unit_id to order_items to track which unit was sold ──────────
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignUuid('product_unit_id')
                  ->nullable()
                  ->after('product_id')
                  ->constrained('product_units')
                  ->nullOnDelete();
            $table->string('unit_name', 50)->nullable()->after('product_name'); // snapshot
            $table->decimal('qty_per_base', 12, 3)->default(1)->after('unit_name'); // snapshot
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_unit_id']);
            $table->dropColumn(['product_unit_id', 'unit_name', 'qty_per_base']);
        });
        Schema::dropIfExists('product_units');
    }
};
