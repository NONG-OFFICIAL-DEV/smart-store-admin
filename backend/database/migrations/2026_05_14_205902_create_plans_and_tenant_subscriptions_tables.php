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
        | Plans
        |--------------------------------------------------------------------------
        */
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name', 100);
            $table->string('code', 50)->unique();

            $table->decimal('price_usd', 10, 2)->default(0);
            $table->bigInteger('price_khr')->default(0);


            $table->integer('seats')->default(1);
            $table->integer('storage_gb')->default(0);
            $table->integer('api_limit')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Tenant Subscriptions
        |--------------------------------------------------------------------------
        */
        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('tenant_id');
            $table->uuid('plan_id');

            $table->enum('status', [
                'active',
                'trial',
                'suspended',
                'cancelled'
            ])->default('trial');

            $table->timestamp('trial_ends_at')->nullable();

            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();

            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */
            $table->index('tenant_id');
            $table->index('plan_id');
            $table->index('status');

            /*
            |--------------------------------------------------------------------------
            | Foreign Keys
            |--------------------------------------------------------------------------
            */
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('plan_id')
                ->references('id')
                ->on('plans')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('plans');
    }
};
