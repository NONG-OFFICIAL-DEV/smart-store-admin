<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Invoices
        |--------------------------------------------------------------------------
        */
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('invoice_number', 30)->unique();

            $table->uuid('tenant_id');
            $table->uuid('subscription_id');

            $table->decimal('amount_usd', 10, 2)->default(0);
            $table->bigInteger('amount_khr')->default(0);

            $table->enum('currency', [
                'USD',
                'KHR'
            ])->default('USD');

            $table->enum('status', [
                'draft',
                'pending',
                'paid',
                'overdue',
                'void'
            ])->default('pending');

            $table->date('due_date')->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->string('pdf_url', 500)->nullable();

            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index('subscription_id');
            $table->index('status');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('subscription_id')
                ->references('id')
                ->on('tenant_subscriptions')
                ->cascadeOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | Payment Transactions
        |--------------------------------------------------------------------------
        */
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('invoice_id');
            $table->uuid('tenant_id');

            $table->enum('gateway', [
                'aba',
                'bakong'
            ]);

            $table->string('gateway_txn_id', 100)->nullable();

            $table->text('qr_string')->nullable();

            $table->string('qr_image_url', 500)->nullable();

            $table->decimal('amount_usd', 10, 2)->default(0);
            $table->bigInteger('amount_khr')->default(0);

            $table->enum('currency', [
                'USD',
                'KHR'
            ])->default('USD');

            $table->enum('status', [
                'pending',
                'paid',
                'expired',
                'failed'
            ])->default('pending');

            $table->timestamp('qr_expires_at')->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->json('webhook_payload')->nullable();

            $table->timestamps();

            $table->index('invoice_id');
            $table->index('tenant_id');
            $table->index('gateway');
            $table->index('gateway_txn_id');
            $table->index('status');

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->cascadeOnDelete();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | Payment Webhooks
        |--------------------------------------------------------------------------
        */
        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->enum('gateway', [
                'aba',
                'bakong'
            ]);

            $table->string('gateway_txn_id', 100)->nullable();

            $table->string('event_type', 60)->nullable();

            $table->json('raw_payload');

            $table->string('signature', 256)->nullable();

            $table->boolean('verified')->default(false);

            $table->boolean('processed')->default(false);

            $table->timestamp('received_at')->useCurrent();

            $table->timestamps();

            $table->index('gateway');
            $table->index('gateway_txn_id');
            $table->index('verified');
            $table->index('processed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhooks');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('invoices');
    }
};
