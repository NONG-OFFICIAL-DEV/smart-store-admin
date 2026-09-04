<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_types', function (Blueprint $table) {
            $table->string('category', 20)->nullable()->after('icon');
        });

        // Backfill existing rows to match the classification the frontend's
        // static constants/businessTypes.js map already assigned them — this
        // migration is what makes that mapping DB-driven going forward, so
        // existing tenants must keep the same food/mart routing they had.
        $foodCodes = ['RESTAURANT', 'COFFEE_SHOP', 'BAKERY', 'KIOSK', 'FOOD_TRUCK'];
        $martCodes = ['MART', 'MINIMART', 'RETAIL', 'WHOLESALE'];

        DB::table('business_types')->whereIn('code', $foodCodes)->update(['category' => 'food']);
        DB::table('business_types')->whereIn('code', $martCodes)->update(['category' => 'mart']);
    }

    public function down(): void
    {
        Schema::table('business_types', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
