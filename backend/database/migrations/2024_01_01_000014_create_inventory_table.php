<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Suppliers ──────────────────────────────────────────────────────────
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('contact_person', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('payment_terms', 100)->nullable()->comment('e.g. Net 30, COD');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();

            $table->index('tenant_id');
        });

        // ── Ingredients ────────────────────────────────────────────────────────
        Schema::create('ingredients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('category', 80)->nullable()->comment('e.g. dairy, produce, packaging');
            $table->string('unit', 30)->comment('kg, g, L, ml, pcs, box');
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->decimal('reorder_point', 12, 3)->nullable();
            $table->decimal('reorder_quantity', 12, 3)->nullable();
            $table->foreignUuid('preferred_supplier_id')->nullable()
                  ->constrained('suppliers')->nullOnDelete();
            $table->string('barcode', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['tenant_id', 'category']);
        });

        // ── Inventory Stock ────────────────────────────────────────────────────
        Schema::create('inventory_stock', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 14, 4)->default(0.0000);
            $table->decimal('quantity_reserved', 14, 4)->default(0.0000);
            $table->timestampTz('last_counted_at')->nullable();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['branch_id', 'ingredient_id']);
            $table->index(['branch_id', 'ingredient_id']);
        });

        // ── Inventory Transactions (ledger) ────────────────────────────────────
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->enum('transaction_type', [
                'purchase', 'usage', 'waste', 'adjustment',
                'transfer_in', 'transfer_out', 'count'
            ]);
            $table->decimal('quantity', 14, 4)
                  ->comment('Positive = stock in, Negative = stock out');
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->string('reference_type', 50)->nullable()
                  ->comment('order | purchase_order | waste_log | stock_count');
            $table->uuid('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['branch_id', 'ingredient_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        // ── Product Recipes (BOM) ──────────────────────────────────────────────
        Schema::create('product_recipes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('variant_id')->nullable()
                  ->constrained('product_variants')->cascadeOnDelete();
            $table->foreignUuid('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->decimal('quantity', 12, 4)->comment('Amount consumed per 1 unit sold');
            $table->string('unit', 30);
            $table->text('notes')->nullable();

            $table->index('product_id');
            $table->index('ingredient_id');
        });

        // ── Purchase Orders ────────────────────────────────────────────────────
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('po_number', 30)->unique();
            $table->enum('status', [
                'draft', 'submitted', 'confirmed',
                'partially_received', 'received', 'cancelled'
            ])->default('draft');
            $table->date('expected_delivery')->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['branch_id', 'status']);
            $table->index('supplier_id');
        });

        // ── Purchase Order Line Items ──────────────────────────────────────────
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignUuid('ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->decimal('quantity_ordered', 12, 3);
            $table->decimal('quantity_received', 12, 3)->default(0.000);
            $table->decimal('unit_price', 12, 4);
            $table->decimal('total_price', 12, 2);
            $table->timestampTz('received_at')->nullable();

            $table->index('purchase_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('product_recipes');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventory_stock');
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('suppliers');
    }
};
