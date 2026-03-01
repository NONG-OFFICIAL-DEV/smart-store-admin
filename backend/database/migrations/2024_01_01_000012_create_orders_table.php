<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Orders ─────────────────────────────────────────────────────────────
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('order_number', 20)->unique();
            $table->enum('order_type', ['dine_in', 'takeaway', 'delivery', 'online'])->default('dine_in');
            $table->enum('status', [
                'draft', 'pending', 'confirmed', 'preparing',
                'ready', 'served', 'completed', 'cancelled', 'refunded'
            ])->default('pending');
            $table->foreignUuid('table_id')->nullable()->constrained('tables')->nullOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignUuid('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignUuid('cashier_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignUuid('delivery_address_id')->nullable()
                  ->constrained('customer_addresses')->nullOnDelete();
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('tax_amount', 12, 2)->default(0.00);
            $table->decimal('service_charge_amount', 12, 2)->default(0.00);
            $table->decimal('delivery_fee', 12, 2)->default(0.00);
            $table->decimal('tips_amount', 12, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->string('coupon_code', 50)->nullable();
            $table->integer('loyalty_points_earned')->nullable();
            $table->integer('loyalty_points_redeemed')->nullable();
            $table->enum('source', ['pos', 'kiosk', 'mobile_app', 'web', 'third_party'])->default('pos');
            $table->timestampTz('estimated_ready_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['branch_id', 'status', 'created_at']);
            $table->index(['customer_id', 'created_at']);
            $table->index('table_id');
            $table->index('order_number');
        });

        // ── Order Items ────────────────────────────────────────────────────────
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUuid('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name', 200)->comment('Snapshot at time of order');
            $table->smallInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('total_price', 12, 2);
            $table->enum('status', ['pending', 'preparing', 'ready', 'served', 'cancelled'])
                  ->default('pending');
            $table->text('notes')->nullable();
            $table->smallInteger('course')->nullable()->comment('Course number for multi-course dining');
            $table->timestampTz('created_at')->useCurrent();

            $table->index('order_id');
            $table->index('product_id');
        });

        // ── Order Item Modifiers ───────────────────────────────────────────────
        Schema::create('order_item_modifiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignUuid('modifier_option_id')->constrained('modifier_options')->restrictOnDelete();
            $table->string('option_name', 100)->comment('Snapshot at time of order');
            $table->decimal('price_adjustment', 10, 2)->default(0.00);
            $table->smallInteger('quantity')->default(1);

            $table->index('order_item_id');
        });

        // ── Order Status History ───────────────────────────────────────────────
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignUuid('changed_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestampTz('changed_at')->useCurrent();

            $table->index(['order_id', 'changed_at']);
        });

        // ── Kitchen Display System Tickets ─────────────────────────────────────
        Schema::create('kitchen_display_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('station', 60)->nullable()->comment('e.g. grill, bar, pastry');
            $table->enum('status', ['new', 'in_progress', 'done', 'cancelled'])->default('new');
            $table->smallInteger('priority')->default(5)->comment('1=urgent, 5=normal');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['branch_id', 'status', 'created_at']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_display_tickets');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_item_modifiers');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
