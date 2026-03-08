<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Step 1: Add as NULLABLE first ─────────────────────────────────────
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('queue_number')->nullable()->after('branch_id')->comment('Incremental number for branch/day');
            $table->index('queue_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('queue_number');
        });
    }
};
