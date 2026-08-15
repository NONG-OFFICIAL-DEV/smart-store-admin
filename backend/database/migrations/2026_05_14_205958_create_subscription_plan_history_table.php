<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plan_history', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('tenant_id');

            $table->uuid('from_plan_id')->nullable();

            $table->uuid('to_plan_id');

            $table->uuid('changed_by')->nullable();

            $table->string('reason', 255)->nullable();

            $table->timestamp('changed_at')->useCurrent();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */
            $table->index('tenant_id');
            $table->index('from_plan_id');
            $table->index('to_plan_id');
            $table->index('changed_by');

            /*
            |--------------------------------------------------------------------------
            | Foreign Keys
            |--------------------------------------------------------------------------
            */
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('from_plan_id')
                ->references('id')
                ->on('plans')
                ->nullOnDelete();

            $table->foreign('to_plan_id')
                ->references('id')
                ->on('plans')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Optional users relation
            |--------------------------------------------------------------------------
            */
            // $table->foreign('changed_by')
            //     ->references('id')
            //     ->on('users')
            //     ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_history');
    }
};
