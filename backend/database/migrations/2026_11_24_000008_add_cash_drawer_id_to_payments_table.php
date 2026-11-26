<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Nullable — only ever set for payment_method='cash' going
            // forward (see CoffeePOSorderController/MartPosController); every
            // other payment method, and any payment made before this
            // migration, has no drawer session to attribute to.
            // nullOnDelete: a deleted drawer shouldn't take a completed
            // payment record down with it.
            $table->foreignUuid('cash_drawer_id')->nullable()
                ->after('staff_id')
                ->constrained('cash_drawers')
                ->nullOnDelete();

            $table->index('cash_drawer_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['cash_drawer_id']);
            $table->dropConstrainedForeignId('cash_drawer_id');
        });
    }
};
