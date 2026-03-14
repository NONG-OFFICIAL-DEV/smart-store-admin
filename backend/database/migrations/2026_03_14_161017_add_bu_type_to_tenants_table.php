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
        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('bu_type', [
                'restaurant',
                'minimart',
                'retail',
                'wholesale',
                'cafe',
                'bakery',
                'kiosk',
                'food_truck'
            ])->default('restaurant')->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('bu_type');
        });
    }
};
