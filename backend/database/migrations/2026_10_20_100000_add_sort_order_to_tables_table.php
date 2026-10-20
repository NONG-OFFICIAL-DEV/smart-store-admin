<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->smallInteger('sort_order')->default(0);
        });

        // Seed a deterministic initial order (by table_number, per branch)
        // instead of leaving every row at 0 — otherwise the first time
        // anyone opens the new reorder view, the order would be whatever
        // the DB happens to return it in, not something sensible.
        DB::statement(<<<'SQL'
            UPDATE tables t
            SET sort_order = sub.rn
            FROM (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY branch_id ORDER BY table_number) - 1 AS rn
                FROM tables
            ) sub
            WHERE t.id = sub.id
        SQL);
    }

    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
