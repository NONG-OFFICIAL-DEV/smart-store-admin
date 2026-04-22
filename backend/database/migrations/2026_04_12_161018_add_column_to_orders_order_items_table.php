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
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('customer_type', ['retail', 'wholesale','lid_exchange', 'mixed'])
                ->default('retail')->after('source');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->enum('customer_type', ['retail', 'wholesale', 'lid_exchange'])
                ->default('retail')->after('quantity');
            $table->boolean('is_lid_exchange')->default(false)->after('customer_type');
            $table->decimal('topup_amount', 12, 2)->nullable()->after('is_lid_exchange');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('customer_type');
        });
        Schema::table('order_items', function (Blueprint $table) {
           $table->dropColumn([
                'customer_type',
                'is_lid_exchange',
                'topup_amount',
            ]);
        });
    }
};
