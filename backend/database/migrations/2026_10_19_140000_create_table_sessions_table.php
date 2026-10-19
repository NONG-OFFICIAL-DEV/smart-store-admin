<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('table_id')->constrained('tables')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();
            $table->foreignUuid('opened_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['table_id', 'status']);
            $table->index(['branch_id', 'status']);
        });

        // A table can only ever have one tab running at a time — enforced at
        // the DB level (not just in TableSessionService::getOrCreateOpenSession())
        // so a race between two concurrent "place order" requests can't ever
        // produce two open sessions for the same table. Partial index since
        // Postgres has no native "unique except when closed" constraint.
        DB::statement('CREATE UNIQUE INDEX table_sessions_one_open_per_table ON table_sessions (table_id) WHERE status = \'open\'');
    }

    public function down(): void
    {
        Schema::dropIfExists('table_sessions');
    }
};
