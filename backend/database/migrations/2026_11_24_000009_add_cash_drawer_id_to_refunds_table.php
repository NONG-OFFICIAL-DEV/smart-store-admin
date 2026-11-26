<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            // Nullable — only ever set when method='cash' (RefundService
            // rejects a cash refund if no drawer is currently open, so a
            // non-null value here always means "physically paid out of this
            // drawer session"). Attributed to whichever drawer is OPEN at
            // refund time, not the one open at the original sale — the cash
            // physically leaves whatever till is open right now.
            $table->foreignUuid('cash_drawer_id')->nullable()
                ->after('staff_id')
                ->constrained('cash_drawers')
                ->nullOnDelete();

            $table->index('cash_drawer_id');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropIndex(['cash_drawer_id']);
            $table->dropConstrainedForeignId('cash_drawer_id');
        });
    }
};
