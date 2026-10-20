<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,       // global permission catalog
            BusinessTypeSeeder::class,     // Restaurant, Coffee Shop, Mart
            BranchTypeSeeder::class,       // branch types per business (depends on BusinessType)
            FeatureSeeder::class,          // feature catalog (POS, KDS, TABLE_MGMT, etc.)
            BranchTypeFeatureSeeder::class,// links branch types → features (depends on both above)
            PlanSeeder::class,             // subscription plans + billing cycles
            UserSeeder::class,             // super admin account
        ]);
    }
}
