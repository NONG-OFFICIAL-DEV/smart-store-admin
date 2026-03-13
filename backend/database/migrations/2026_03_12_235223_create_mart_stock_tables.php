<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Mart Purchase Orders ───────────────────────────────────────────────
        Schema::create('mart_purchase_orders', function (Blueprint $table) {
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
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by_staff_id')->nullable()
                  ->constrained('staff')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['branch_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });

        // ── Mart PO Line Items (product + unit based) ─────────────────────────
        Schema::create('mart_purchase_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mart_purchase_order_id')
                  ->constrained('mart_purchase_orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUuid('product_unit_id')->nullable()
                  ->constrained('product_units')->nullOnDelete();

            // Snapshots (in case product/unit changes later)
            $table->string('product_name', 200);
            $table->string('unit_name', 50)->nullable();     // can, box, pallet
            $table->decimal('qty_per_base', 12, 3)->default(1); // how many base units

            $table->decimal('quantity_ordered', 12, 3);
            $table->decimal('quantity_received', 12, 3)->default(0);
            $table->decimal('unit_cost', 12, 4);             // cost per unit
            $table->decimal('total_cost', 12, 2);
            $table->timestampTz('received_at')->nullable();

            $table->index('mart_purchase_order_id');
            $table->index('product_id');
        });

        // ── Stock Movements (full ledger) ─────────────────────────────────────
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();

            $table->enum('movement_type', [
                'purchase',        // stock received from PO
                'sale',            // deducted by POS order
                'adjustment_in',   // manual add
                'adjustment_out',  // manual remove
                'waste',           // expired/damaged
                'transfer_in',     // from another branch
                'transfer_out',    // to another branch
                'count',           // stock count correction
            ]);

            // Always in BASE units (e.g. cans, not boxes)
            $table->decimal('quantity', 14, 4)
                  ->comment('Positive = stock in, Negative = stock out');
            $table->decimal('qty_before', 14, 4)->default(0);
            $table->decimal('qty_after', 14, 4)->default(0);
            $table->decimal('unit_cost', 12, 4)->nullable();

            // Reference back to source
            $table->string('reference_type', 50)->nullable()
                  ->comment('mart_purchase_order | order | adjustment');
            $table->uuid('reference_id')->nullable();

            $table->text('notes')->nullable();
            $table->foreignUuid('staff_id')->nullable()
                  ->constrained('staff')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['branch_id', 'product_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('mart_purchase_order_items');
        Schema::dropIfExists('mart_purchase_orders');
    }
};