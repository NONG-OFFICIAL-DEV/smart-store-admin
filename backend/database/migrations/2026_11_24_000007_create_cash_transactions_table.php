<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only audit log of manual cash in/out during an open shift —
        // never updated or deleted (no updated_at column, no update/destroy
        // routes). System-generated cash-outs from a cash refund are NOT
        // recorded here — those live on refunds.cash_drawer_id instead, see
        // the refunds migration below.
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cash_drawer_id')->constrained('cash_drawers')->cascadeOnDelete();
            $table->foreignUuid('staff_id')->constrained('staff')->restrictOnDelete();
            $table->enum('type', ['cash_in', 'cash_out']);
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            $table->timestampTz('created_at')->useCurrent();

            $table->index('cash_drawer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
    }
};
