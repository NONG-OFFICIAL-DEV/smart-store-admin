<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('loyalty_reward_id')->constrained('loyalty_rewards')->cascadeOnDelete();
            // Short code shown alongside the QR (Stitch design's "Manual
            // Code: MX4-82K") — the cashier can type this in if scanning
            // fails. Unique only while pending; a redeemed/expired/
            // cancelled code can't be mistaken for a live one.
            $table->string('code', 8)->unique();
            $table->enum('status', ['pending', 'redeemed', 'cancelled', 'expired'])->default('pending');
            $table->timestampTz('expires_at');
            $table->timestampTz('redeemed_at')->nullable();
            $table->foreignUuid('redeemed_by_staff_id')->nullable()->constrained('users')->nullOnDelete();
            // Points were already deducted via Customer::redeemPoints() by
            // the time this is set — the ledger entry it created.
            $table->foreignUuid('loyalty_transaction_id')->nullable()->constrained('loyalty_transactions')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_redemptions');
    }
};
