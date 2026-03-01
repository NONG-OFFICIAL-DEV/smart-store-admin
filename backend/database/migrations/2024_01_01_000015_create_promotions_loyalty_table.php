<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Promotions ─────────────────────────────────────────────────────────
        Schema::create('promotions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->enum('type', [
                'percentage', 'fixed_amount', 'bogo',
                'free_item', 'combo', 'happy_hour'
            ]);
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->decimal('min_order_amount', 12, 2)->nullable();
            $table->decimal('max_discount_amount', 12, 2)->nullable();
            $table->enum('applies_to', ['all', 'categories', 'products', 'order'])->default('all');
            $table->json('applicable_ids')->nullable()
                  ->comment('Array of product or category UUIDs');
            $table->timestampTz('start_at');
            $table->timestampTz('end_at')->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->integer('per_customer_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['start_at', 'end_at']);
        });

        // ── Coupons ────────────────────────────────────────────────────────────
        Schema::create('coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->string('code', 50)->unique();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('code');
        });

        // ── Coupon Usages ──────────────────────────────────────────────────────
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->decimal('discount_applied', 12, 2);
            $table->timestampTz('used_at')->useCurrent();

            $table->index(['coupon_id', 'customer_id']);
            $table->index('order_id');
        });

        // ── Loyalty Transactions ───────────────────────────────────────────────
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->enum('type', ['earn', 'redeem', 'expire', 'adjust', 'bonus']);
            $table->integer('points')
                  ->comment('Positive = earn, Negative = redeem/expire');
            $table->integer('balance_after');
            $table->text('description')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['customer_id', 'created_at']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('promotions');
    }
};
