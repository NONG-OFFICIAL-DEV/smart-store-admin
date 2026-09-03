<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cambodian branches are given as one free-text address line, not split
// into address_line1/address_line2/city/state/postal_code — the branch
// form no longer collects `city` separately, so it can no longer be
// required at the DB level either.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('city', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('city', 100)->nullable(false)->change();
        });
    }
};
