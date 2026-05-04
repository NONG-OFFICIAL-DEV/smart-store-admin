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
        Schema::create('pin_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->string('terminal_id', 100);
            $table->unsignedTinyInteger('fail_count')->default(0);
            $table->timestampTz('locked_until')->nullable();
            $table->timestampTz('last_attempt_at')->nullable();
            $table->timestampsTz();

            $table->unique(['branch_id', 'terminal_id']);
            $table->index('locked_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pin_attempts');
    }
};
