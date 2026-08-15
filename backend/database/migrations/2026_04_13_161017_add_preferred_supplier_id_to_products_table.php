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
        Schema::table('products', function (Blueprint $table) {
            $table->uuid('preferred_supplier_id')->nullable();

            $table->foreign('preferred_supplier_id')
                ->references('id')
                ->on('suppliers')
                ->nullOnDelete();

            $table->timestamp('preferred_supplier_assigned_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['preferred_supplier_id']);
            $table->dropColumn('preferred_supplier_id');
            $table->dropColumn('preferred_supplier_assigned_at');
        });
    }
};
