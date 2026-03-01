<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Customers ──────────────────────────────────────────────────────────
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('first_name', 80);
            $table->string('last_name', 80)->nullable();
            $table->string('email', 255)->nullable()->unique();
            $table->string('phone', 30)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'non_binary', 'prefer_not_to_say'])->nullable();
            $table->text('avatar_url')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_spent', 14, 2)->default(0.00);
            $table->integer('total_orders')->default(0);
            $table->integer('loyalty_points')->default(0);
            $table->string('loyalty_tier', 30)->nullable();
            $table->boolean('marketing_opt_in')->default(false);
            $table->string('preferred_language', 10)->nullable();
            $table->enum('source', ['walk_in', 'online', 'referral', 'import'])->default('walk_in');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'phone']);
        });

        // ── Customer Addresses ─────────────────────────────────────────────────
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('label', 60)->nullable();
            $table->string('address_line1', 255);
            $table->string('address_line2', 255)->nullable();
            $table->string('city', 100);
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country', 2)->default('US');
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->boolean('is_default')->default(false);

            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
