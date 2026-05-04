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
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('actor_type', 20);
            $table->uuid('actor_id');
            $table->string('terminal_id', 100);
            $table->string('token_hash', 64)->unique(); // sha256 of raw token
            $table->boolean('is_revoked')->default(false);
            $table->timestampTz('expires_at');
            $table->timestampTz('rotated_at')->nullable();
            $table->timestampsTz();

            $table->index(['actor_id', 'actor_type']);
            $table->index(['terminal_id', 'is_revoked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
