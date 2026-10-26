<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Snapshot at time of order, same pattern as product_name —
            // order_items had no column for this at all, so the size
            // selected on a food/coffee POS sale was only ever visible in
            // the immediate print response, never on a later bill/reprint.
            $table->string('variant_name', 80)->nullable()->after('product_name');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('variant_name');
        });
    }
};
