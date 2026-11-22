<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A dedicated table rather than another tenants.pos_settings key — the
// program now has enough of its own fields (earning rule *type*, several
// notification toggles) to deserve its own admin section ("POS Settings →
// Loyalty") and its own row, separate from the generic POS checkout prefs.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            // per_order: earning_rate stamps per completed order (the only
            // rule implemented until Order carries per-item/currency
            // granularity worth trusting — see earning_rate's comment).
            $table->enum('earning_type', ['per_order', 'per_currency'])->default('per_order');
            // per_order: stamps per order. per_currency: stamps per this
            // many currency units spent (e.g. 1 stamp per 2000 riel).
            $table->decimal('earning_rate', 10, 2)->default(1);
            $table->boolean('notify_stamps_earned')->default(true);
            $table->boolean('notify_reward_ready')->default(true);
            $table->boolean('notify_reward_expiring')->default(true);
            $table->boolean('notify_birthday')->default(true);
            $table->timestampsTz();
        });

        // Backfill from the existing tenants.pos_settings.loyalty_points_per_order
        // so a tenant that already configured this doesn't silently reset to
        // the default on upgrade.
        DB::statement("
            INSERT INTO loyalty_settings (id, tenant_id, earning_type, earning_rate, created_at, updated_at)
            SELECT gen_random_uuid(), id, 'per_order',
                   COALESCE((pos_settings->>'loyalty_points_per_order')::numeric, 1),
                   now(), now()
            FROM tenants
            WHERE NOT EXISTS (SELECT 1 FROM loyalty_settings WHERE loyalty_settings.tenant_id = tenants.id)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_settings');
    }
};
