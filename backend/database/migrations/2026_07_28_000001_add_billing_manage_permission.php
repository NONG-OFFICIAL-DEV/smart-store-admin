<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    // Idempotent — safe to run on already-seeded databases. Creating this
    // permission fires Permission::created, which (see Permission.php's
    // boot()) automatically attaches it to every tenant's Owner role via
    // OwnerRoleProvisioner — no separate backfill loop needed here.
    public function up(): void
    {
        Permission::updateOrCreate(
            ['code' => 'billing.manage'],
            ['group' => 'Billing', 'description' => 'Renew the subscription and change (upgrade/downgrade) the business plan.']
        );
    }

    public function down(): void
    {
        Permission::where('code', 'billing.manage')->delete();
    }
};
