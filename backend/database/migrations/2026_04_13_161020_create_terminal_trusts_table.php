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
        Schema::create('terminal_trusts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained()->cascadeOnDelete(); // ← nullable
            $table->string('actor_type', 20);       // 'staff' | 'owner'
            $table->uuid('actor_id');               // staff.id or tenant.id
            $table->string('terminal_id', 100);     // device fingerprint from client
            $table->string('device_name', 120)->nullable();
            $table->boolean('is_revoked')->default(false);
            $table->timestampTz('trusted_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampsTz();

            $table->unique(['branch_id', 'terminal_id', 'actor_id', 'actor_type'], 'terminal_actor_unique');
            $table->index(['terminal_id', 'is_revoked']);
            $table->index(['actor_id', 'actor_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_trusts');
    }
};
