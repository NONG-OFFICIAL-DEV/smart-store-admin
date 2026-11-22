<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_redemptions', function (Blueprint $table) {
            // Which order this reward actually got applied to (POS) and
            // which branch confirmed it — both nullable since a redemption
            // can still be pending/cancelled/expired with neither set.
            $table->foreignUuid('order_id')->nullable()->after('loyalty_reward_id')
                ->constrained('orders')->nullOnDelete();
            $table->foreignUuid('branch_id')->nullable()->after('order_id')
                ->constrained('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_redemptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
