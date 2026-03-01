<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Daily Sales Summary ────────────────────────────────────────────────
        Schema::create('daily_sales_summary', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->date('date');
            $table->integer('total_orders')->default(0);
            $table->decimal('total_revenue', 14, 2)->default(0.00);
            $table->decimal('total_discount', 14, 2)->default(0.00);
            $table->decimal('total_tax', 14, 2)->default(0.00);
            $table->decimal('total_tips', 14, 2)->default(0.00);
            $table->decimal('net_revenue', 14, 2)->default(0.00);
            $table->decimal('total_cogs', 14, 2)->nullable();
            $table->decimal('gross_profit', 14, 2)->nullable();
            $table->decimal('avg_order_value', 10, 2)->nullable();
            $table->integer('dine_in_orders')->default(0);
            $table->integer('takeaway_orders')->default(0);
            $table->integer('delivery_orders')->default(0);
            $table->integer('new_customers')->default(0);
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['branch_id', 'date']);
            $table->index(['branch_id', 'date']);
        });

        // ── Activity Logs (audit trail) ────────────────────────────────────────
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100)->comment('e.g. order.created, product.deleted');
            $table->string('entity_type', 60)->nullable();
            $table->uuid('entity_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->jsonb('payload')->nullable()->comment('Before/after state snapshot');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['tenant_id', 'entity_type', 'entity_id']);
            $table->index(['tenant_id', 'user_id', 'created_at']);
            $table->index(['tenant_id', 'action', 'created_at']);
        });

        // ── Notifications ──────────────────────────────────────────────────────
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('role_id')->nullable()->constrained('roles')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('type', 60)
                  ->comment('e.g. low_stock, new_order, shift_reminder, payment_failed');
            $table->string('title', 200);
            $table->text('body');
            $table->jsonb('data')->nullable()->comment('Structured payload for deep linking');
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['user_id', 'read_at', 'created_at']);
            $table->index(['tenant_id', 'type', 'created_at']);
            $table->index(['branch_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('daily_sales_summary');
    }
};
