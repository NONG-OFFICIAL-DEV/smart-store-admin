<?php

use App\Models\Tenant;
use App\Services\OwnerRoleProvisioner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('code', 40)->nullable()->after('tenant_id');
            $table->unique(['tenant_id', 'code']);
        });

        // Backfill — every existing tenant already has owner_user_id set
        // (required since tenant creation), so we only need to provision the
        // missing Owner role row + attach it to the full permission catalog.
        // No manual DB step required beyond running this migration.
        $provisioner = app(OwnerRoleProvisioner::class);
        Tenant::withoutGlobalScopes()->cursor()->each(
            fn (Tenant $tenant) => $provisioner->ensureFor($tenant)
        );
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'code']);
            $table->dropColumn('code');
        });
    }
};
