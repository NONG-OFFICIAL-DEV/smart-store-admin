<?php

use App\Models\Branch;
use App\Models\BranchType;
use Database\Seeders\BranchTypeFeatureSeeder;
use Database\Seeders\BranchTypeSeeder;
use Database\Seeders\BusinessTypeSeeder;
use Database\Seeders\FeatureSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Production only ever runs `migrate`, never `db:seed` — so
     * business_types/branch_types/branch_type_features (and therefore
     * every branch's branch_type_id) were never populated there. That
     * silently disabled every branch-type-gated nav item (Table
     * Management, Reservations, Kitchen Display) for every tenant, not
     * just restaurants. All four seeders are idempotent
     * (firstOrCreate/upsert keyed on `code`), safe to run against data
     * that's partially or fully seeded already.
     */
    public function up(): void
    {
        Artisan::call('db:seed', ['--class' => BusinessTypeSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => FeatureSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => BranchTypeSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => BranchTypeFeatureSeeder::class, '--force' => true]);

        // Seeding the catalog doesn't fix branches created before it
        // existed — backfill each one to its business type's HQ branch
        // type (the same default TenantService::create() would have
        // assigned had the catalog existed at creation time). An owner
        // can refine a specific branch's type afterward via Branch
        // Management if it's actually a delivery/popup branch, not an HQ.
        Branch::withoutGlobalScopes()
            ->whereNull('branch_type_id')
            ->with('tenant:id,business_type_id')
            ->get()
            ->each(function (Branch $branch) {
                $businessTypeId = $branch->tenant?->business_type_id;
                if (! $businessTypeId) {
                    return;
                }

                $hqBranchTypeId = BranchType::where('business_type_id', $businessTypeId)
                    ->where('is_hq', true)
                    ->value('id');

                if ($hqBranchTypeId) {
                    $branch->update(['branch_type_id' => $hqBranchTypeId]);
                }
            });
    }

    /**
     * Not reversible — seeding/backfilling catalog data is one-directional.
     */
    public function down(): void
    {
        //
    }
};
