<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Calories removed from the product form/detail page/digital menu —
     * nothing in the app reads or writes this column anymore.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('calories');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->smallInteger('calories')->nullable()->after('preparation_time');
        });
    }
};
