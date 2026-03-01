<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Floor Plans ────────────────────────────────────────────────────────
        Schema::create('floor_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('name', 100);
            $table->smallInteger('sort_order')->default(0);
            $table->jsonb('layout_json')->nullable()->comment('Canvas data: positions, walls');

            $table->index('branch_id');
        });

        // ── Tables ─────────────────────────────────────────────────────────────
        Schema::create('tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('floor_plan_id')->nullable()->constrained('floor_plans')->nullOnDelete();
            $table->string('table_number', 20);
            $table->smallInteger('capacity')->default(4);
            $table->enum('shape', ['round', 'square', 'rectangle', 'bar'])->nullable();
            $table->smallInteger('position_x')->nullable();
            $table->smallInteger('position_y')->nullable();
            $table->text('qr_code')->nullable();
            $table->enum('status', ['available', 'occupied', 'reserved', 'cleaning', 'inactive'])
                  ->default('available');
            $table->boolean('is_active')->default(true);

            $table->unique(['branch_id', 'table_number']);
            $table->index(['branch_id', 'status']);
        });

        // ── Reservations ───────────────────────────────────────────────────────
        Schema::create('reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('table_id')->nullable()->constrained('tables')->nullOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name', 150);
            $table->string('customer_phone', 30)->nullable();
            $table->smallInteger('party_size');
            $table->timestampTz('reserved_at');
            $table->smallInteger('duration_minutes')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'seated', 'completed', 'no_show', 'cancelled'])
                  ->default('pending');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['branch_id', 'reserved_at', 'status']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('tables');
        Schema::dropIfExists('floor_plans');
    }
};
