<?php

namespace App\Services;

use App\Repositories\Contracts\InventoryStockRepositoryInterface;
use App\Repositories\Contracts\InventoryTransactionRepositoryInterface;

/**
 * Ingredient-based inventory report (InventoryStock/InventoryTransaction —
 * restaurant/coffee-shop tenants). Deliberately separate from Mart's own
 * product/stock-movement inventory report (mart/reports/inventory,
 * MartPosController::reportStock) — the two stock systems are not merged,
 * per this project's own documented reconciliation risk.
 */
class InventoryReportService
{
    public function __construct(
        private InventoryStockRepositoryInterface $stockRepo,
        private InventoryTransactionRepositoryInterface $transactionRepo,
    ) {
    }

    public function report(array $filters): array
    {
        $branchId = $filters['branch_id'] ?? null;
        [$from, $to] = $this->resolveDateRange($filters);

        $stocks = $this->stockRepo->query()->with('ingredient')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get();

        // Ingredient::isLowStock() already does the exact reorder-point
        // comparison against a given branch — reused as-is rather than
        // re-deriving it here.
        $lowStock = $stocks->filter(fn ($s) => $s->ingredient?->isLowStock($s->branch_id) ?? false);
        $outOfStock = $stocks->filter(fn ($s) => $s->quantity_on_hand <= 0);

        $movements = $this->transactionRepo->query()->with('ingredient')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('created_at', [$from, $to])
            ->get();

        return [
            'summary' => [
                'total_ingredients' => $stocks->count(),
                'low_stock_count' => $lowStock->count(),
                'out_of_stock_count' => $outOfStock->count(),
            ],
            'stock' => $stocks->values(),
            'low_stock' => $lowStock->values(),
            'movement_summary' => $movements->groupBy('transaction_type')->map(fn ($g) => (float) $g->sum('quantity')),
        ];
    }

    private function resolveDateRange(array $filters): array
    {
        $from = ($filters['date_from'] ?? now()->startOfMonth()->toDateString()).' 00:00:00';
        $to = ($filters['date_to'] ?? now()->toDateString()).' 23:59:59';

        return [$from, $to];
    }
}
