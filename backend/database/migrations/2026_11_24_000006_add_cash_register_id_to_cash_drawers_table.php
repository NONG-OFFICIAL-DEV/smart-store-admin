<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_drawers', function (Blueprint $table) {
            // Nullable — existing rows predate registers, and a tenant that
            // never configures named registers can keep opening branch-level
            // shifts exactly as before. nullOnDelete (not cascade): deleting
            // a register must never wipe out its past shift history.
            $table->foreignUuid('cash_register_id')->nullable()
                ->after('branch_id')
                ->constrained('cash_registers')
                ->nullOnDelete();

            $table->index(['cash_register_id', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('cash_drawers', function (Blueprint $table) {
            $table->dropIndex(['cash_register_id', 'closed_at']);
            $table->dropConstrainedForeignId('cash_register_id');
        });
    }
};
