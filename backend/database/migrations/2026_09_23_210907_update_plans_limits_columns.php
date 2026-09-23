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
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['storage_gb', 'api_limit']);
            // Nullable = unlimited, mirrors how seats already works.
            $table->integer('branches_limit')->nullable()->after('seats');
            $table->integer('products_limit')->nullable()->after('branches_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['branches_limit', 'products_limit']);
            $table->integer('storage_gb')->default(0);
            $table->integer('api_limit')->default(0);
        });
    }
};
