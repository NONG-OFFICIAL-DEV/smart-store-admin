<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_plan_history', function (Blueprint $table) {
            $table->foreignUuid('billing_cycle_id')
                ->nullable()
                ->after('plan_id')
                ->constrained('plan_billing_cycles')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plan_history', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billing_cycle_id');
        });
    }
};
