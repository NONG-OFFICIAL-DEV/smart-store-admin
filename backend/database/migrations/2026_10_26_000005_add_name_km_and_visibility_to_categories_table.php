<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name_km', 100)->nullable()->after('name');
            $table->boolean('show_on_pos')->default(true)->after('is_active');
            $table->boolean('show_on_emenu')->default(true)->after('show_on_pos');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['name_km', 'show_on_pos', 'show_on_emenu']);
        });
    }
};
