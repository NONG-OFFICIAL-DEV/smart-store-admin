<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Products ───────────────────────────────────────────────────────────
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('sku', 60)->unique()->nullable();
            $table->string('barcode', 60)->nullable();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->text('image_url')->nullable();
            $table->decimal('base_price', 12, 2)->default(0.00);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->enum('product_type', ['food', 'beverage', 'retail', 'combo'])->default('food');
            $table->smallInteger('preparation_time')->nullable()->comment('Minutes');
            $table->smallInteger('calories')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->string('tax_category', 50)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['tenant_id', 'category_id', 'is_available']);
            $table->index('barcode');
        });

        // ── Product Variants ───────────────────────────────────────────────────
        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name', 80);
            $table->decimal('price_adjustment', 10, 2)->default(0.00);
            $table->string('sku_suffix', 20)->nullable();
            $table->boolean('is_default')->default(false);
            $table->smallInteger('sort_order')->default(0);

            $table->index('product_id');
        });

        // ── Modifier Groups ────────────────────────────────────────────────────
        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 100);
            $table->enum('selection_type', ['single', 'multiple'])->default('single');
            $table->smallInteger('min_selections')->default(0);
            $table->smallInteger('max_selections')->nullable();
            $table->boolean('is_required')->default(false);

            $table->index('tenant_id');
        });

        // ── Modifier Options ───────────────────────────────────────────────────
        Schema::create('modifier_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('group_id')->constrained('modifier_groups')->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('price_adjustment', 10, 2)->default(0.00);
            $table->boolean('is_available')->default(true);
            $table->smallInteger('sort_order')->default(0);

            $table->index('group_id');
        });

        // ── Product ↔ Modifier Group pivot ────────────────────────────────────
        Schema::create('product_modifier_groups', function (Blueprint $table) {
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('modifier_group_id')->constrained('modifier_groups')->cascadeOnDelete();
            $table->smallInteger('sort_order')->default(0);

            $table->primary(['product_id', 'modifier_group_id']);
        });

        // ── Branch-level product overrides ─────────────────────────────────────
        Schema::create('branch_product_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('override_price', 12, 2)->nullable();
            $table->boolean('is_available')->nullable();

            $table->unique(['branch_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_product_overrides');
        Schema::dropIfExists('product_modifier_groups');
        Schema::dropIfExists('modifier_options');
        Schema::dropIfExists('modifier_groups');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
