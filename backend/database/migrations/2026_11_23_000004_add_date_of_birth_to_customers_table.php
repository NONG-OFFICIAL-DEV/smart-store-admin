<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// date_of_birth was dropped from customers in 2026_09_04 (never used
// anywhere beyond display) — re-added specifically for the loyalty
// program's birthday-gift notification, so it's deliberately not bundled
// back in with gender/preferred_language, which still have no consumer.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('date_of_birth');
        });
    }
};
