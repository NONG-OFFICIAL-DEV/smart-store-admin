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
        Schema::table('business_types', function (Blueprint $table) {
            // Purely cosmetic (an emoji shown on a few admin screens) — no
            // logic in the app branches on it, unlike `category`.
            $table->dropColumn('icon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_types', function (Blueprint $table) {
            $table->string('icon', 10)->nullable()->after('code');
        });
    }
};
