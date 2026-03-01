<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Payments ───────────────────────────────────────────────────────────
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->enum('payment_method', [
                'cash', 'card', 'qr_code', 'online', 'loyalty_points', 'voucher'
            ]);
            $table->decimal('amount', 12, 2);
            $table->decimal('change_given', 12, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 15, 6)->nullable();
            $table->enum('status', [
                'pending', 'completed', 'failed', 'refunded', 'partially_refunded'
            ])->default('pending');
            $table->string('gateway', 60)->nullable()->comment('e.g. Stripe, ABA Pay, KHQR');
            $table->string('gateway_transaction_id', 200)->nullable();
            $table->jsonb('gateway_response')->nullable();
            $table->string('receipt_number', 50)->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['order_id', 'status']);
            $table->index(['branch_id', 'paid_at']);
            $table->index('gateway_transaction_id');
        });

        // ── Refunds ────────────────────────────────────────────────────────────
        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('staff_id')->constrained('staff')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            $table->enum('method', ['original_method', 'cash', 'credit_note', 'loyalty_points'])
                  ->default('original_method');
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->string('gateway_refund_id', 200)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['payment_id', 'status']);
            $table->index('order_id');
        });

        // ── Cash Drawers ───────────────────────────────────────────────────────
        Schema::create('cash_drawers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('staff_id')->constrained('staff')->restrictOnDelete();
            $table->decimal('opening_float', 12, 2)->default(0.00);
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('actual_cash', 12, 2)->nullable();
            $table->decimal('variance', 12, 2)->nullable();
            $table->timestampTz('opened_at')->useCurrent();
            $table->timestampTz('closed_at')->nullable();
            $table->text('notes')->nullable();

            $table->index(['branch_id', 'opened_at']);
            $table->index(['staff_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_drawers');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
    }
};
