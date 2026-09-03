<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The simplified branch form treats address as a single optional line, not
// a mandatory field — small Cambodian shops often don't have a formal one.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('address_line1', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('address_line1', 255)->nullable(false)->change();
        });
    }
};
