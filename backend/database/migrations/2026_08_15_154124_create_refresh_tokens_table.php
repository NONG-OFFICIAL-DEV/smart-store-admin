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
        // Orphaned leftovers from an abandoned, never-merged experiment (a
        // "terminal trust" + PIN-attempt-rate-limiting + refresh-token
        // system, commit 3049c8c — not reachable from any branch, its
        // migration files no longer exist on disk, nothing in app/ or
        // routes/ references any of these three tables). Their presence
        // predates this migration entirely; clean them up before creating
        // the real, correctly-shaped table this migration is actually for.
        Schema::dropIfExists('pin_attempts');
        Schema::dropIfExists('terminal_trusts');
        Schema::dropIfExists('refresh_tokens');

        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            // Shared by every token rotated from one original login — lets
            // reuse detection revoke the whole lineage in one query without
            // a linked-list of replaced-by pointers.
            $table->uuid('family_id');
            // sha256 hex digest — the raw token is only ever seen by the
            // client, never persisted.
            $table->string('token_hash', 64)->unique();
            $table->string('device_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampsTz();

            $table->index('family_id');
            $table->index(['user_id', 'revoked_at', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
