<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        // ══════════════════════════════════════════════════════════════════════
        // shift_templates — reusable shift definitions
        // e.g. "Morning", "Ca Sang", "Night" defined ONCE, reused forever
        // ══════════════════════════════════════════════════════════════════════
        Schema::create('shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('name', 60);                    // "Morning", "Ca Sang"
            $table->string('shift_type', 20)->nullable();  // morning|afternoon|evening|full_day|split
            $table->time('start_time');                    // 09:00 — time only, no date
            $table->time('end_time');                      // 17:00 — time only, no date
            $table->smallInteger('break_minutes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['tenant_id']);
        });

        // ══════════════════════════════════════════════════════════════════════
        // staff_shifts — pivot table assigning staff to a shift on a specific date
        //
        // One shift template → many staff on many dates
        // One staff          → many shift templates on many dates
        // ══════════════════════════════════════════════════════════════════════
        Schema::create('staff_shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignUuid('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();

            // The specific date this assignment is for
            // Time comes from shift_template.start_time / end_time
            $table->date('shift_date');

            // Actual clock in/out — full timestamp needed for exact payroll calc
            $table->timestampTz('actual_start')->nullable();
            $table->timestampTz('actual_end')->nullable();

            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            // Prevent same staff being assigned same shift on same date twice
            $table->unique(['shift_id', 'staff_id', 'shift_date']);

            $table->index(['branch_id', 'shift_date']);
            $table->index(['staff_id', 'shift_date']);
            $table->index('shift_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_shifts');
        Schema::dropIfExists('shifts');
    }
};
