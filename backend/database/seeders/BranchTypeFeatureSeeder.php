<?php

// database/seeders/BranchTypeFeatureSeeder.php

namespace Database\Seeders;

use App\Models\BranchType;
use App\Models\Feature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BranchTypeFeatureSeeder extends Seeder
{
    /**
     * Features that can never be disabled on any branch type.
     */
    private array $requiredFeatures = ['POS', 'MENU'];

    /**
     * Branch type → feature codes mapping.
     */
    private array $map = [
        // ─── Restaurant ──────────────────────────────────────────
        'RESTAURANT_HQ' => [
            'POS', 'MENU', 'KDS', 'TABLE_MGMT',
            'RESERVATION', 'LOYALTY', 'DELIVERY', 'REPORT',
        ],
        'RESTAURANT_DINE_IN' => [
            'POS', 'MENU', 'KDS', 'TABLE_MGMT',
            'RESERVATION', 'LOYALTY', 'REPORT',
        ],
        'RESTAURANT_DELIVERY' => [
            'POS', 'MENU', 'DELIVERY', 'REPORT',
        ],
        'RESTAURANT_POPUP' => [
            'POS', 'MENU',
        ],

        // ─── Coffee Shop ─────────────────────────────────────────
        'COFFEE_HQ' => [
            'POS', 'MENU', 'QUEUE', 'TABLE_MGMT',
            'LOYALTY', 'STAMP_CARD', 'REPORT',
        ],
        'COFFEE_TAKEAWAY' => [
            'POS', 'MENU', 'QUEUE', 'STAMP_CARD', 'REPORT',
        ],
        'COFFEE_CAFE' => [
            'POS', 'MENU', 'TABLE_MGMT', 'LOYALTY', 'STAMP_CARD', 'REPORT',
        ],
        'COFFEE_KIOSK' => [
            'POS', 'MENU', 'QUEUE',
        ],

        // ─── Mart ────────────────────────────────────────────────
        'MART_HQ' => [
            'POS', 'INVENTORY', 'BARCODE', 'SUPPLIER',
            'STOCK_ALERT', 'LOYALTY', 'REPORT',
        ],
        'MART_RETAIL' => [
            'POS', 'INVENTORY', 'BARCODE',
            'STOCK_ALERT', 'LOYALTY', 'REPORT',
        ],
        'MART_MINI' => [
            'POS', 'INVENTORY', 'BARCODE', 'STOCK_ALERT',
        ],
        'MART_ONLINE' => [
            'ONLINE_STORE', 'INVENTORY', 'DELIVERY', 'REPORT',
        ],
    ];

    public function run(): void
    {
        // ── 1. Pre-load all branch types & features into memory ──
        //       Avoids N+1 queries inside the loop
        $branchTypes = BranchType::whereIn('code', array_keys($this->map))
            ->pluck('id', 'code'); // ['RESTAURANT_HQ' => uuid, ...]

        $allFeatureCodes = collect($this->map)->flatten()->unique()->values()->all();

        $features = Feature::whereIn('code', $allFeatureCodes)
            ->pluck('id', 'code'); // ['POS' => uuid, ...]

        // ── 2. Validate — warn if any code is missing ────────────
        foreach (array_keys($this->map) as $branchCode) {
            if (! $branchTypes->has($branchCode)) {
                $this->command->warn("BranchType not found: {$branchCode} — skipping.");
            }
        }

        foreach ($allFeatureCodes as $featureCode) {
            if (! $features->has($featureCode)) {
                $this->command->warn("Feature not found: {$featureCode} — skipping.");
            }
        }

        // ── 3. Build bulk upsert rows ────────────────────────────
        $rows = [];
        $now  = now();

        foreach ($this->map as $branchCode => $featureCodes) {

            $branchTypeId = $branchTypes->get($branchCode);

            if (! $branchTypeId) {
                continue; // already warned above
            }

            foreach ($featureCodes as $featureCode) {

                $featureId = $features->get($featureCode);

                if (! $featureId) {
                    continue; // already warned above
                }

                $rows[] = [
                    'branch_type_id' => $branchTypeId,
                    'feature_id'     => $featureId,
                    'is_required'    => in_array($featureCode, $this->requiredFeatures),
                    'is_default'     => true,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }
        }

        // ── 4. Upsert — safe to re-run (idempotent) ──────────────
        //       Updates is_required & is_default if row already exists
        DB::table('branch_type_features')->upsert(
            $rows,
            uniqueBy: ['branch_type_id', 'feature_id'],  // conflict keys
            update:   ['is_required', 'is_default', 'updated_at'],
        );

        $this->command->info("✅ BranchTypeFeatureSeeder done — {$this->countRows()} rows seeded.");
    }

    private function countRows(): int
    {
        return collect($this->map)->sum(fn($features) => count($features));
    }
}