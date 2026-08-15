<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\InventoryStock;
use App\Models\Notification;
use App\Repositories\Contracts\InventoryStockRepositoryInterface;
use App\Repositories\Contracts\InventoryTransactionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InventoryStockService extends BaseService
{
    public function __construct(
        InventoryStockRepositoryInterface $repository,
        private InventoryTransactionRepositoryInterface $transactions,
    ) {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function byBranch(string $branchId, array $filters): LengthAwarePaginator
    {
        return $this->list(array_merge($filters, ['branch_id' => $branchId]));
    }

    public function byIngredient(string $ingredientId, array $filters): LengthAwarePaginator
    {
        return $this->list(array_merge($filters, ['ingredient_id' => $ingredientId]));
    }

    public function create(array $data): InventoryStock
    {
        return $this->repository->create($data);
    }

    public function update(InventoryStock $stock, array $data): InventoryStock
    {
        return $this->repository->update($stock, $data);
    }

    public function delete(InventoryStock $stock): bool
    {
        return $this->repository->delete($stock);
    }

    /**
     * Adjust on-hand quantity for a branch/ingredient, ledger the movement,
     * and fire a low-stock notification once the new balance crosses the
     * ingredient's reorder point. Moved from the dead (never routed)
     * InventoryStock::adjust() static method.
     */
    public function adjust(string $branchId, string $ingredientId, float $quantity, string $type, ?string $staffId = null, ?string $notes = null): InventoryStock
    {
        $stock = InventoryStock::firstOrCreate(
            ['branch_id' => $branchId, 'ingredient_id' => $ingredientId],
            ['quantity_on_hand' => 0, 'quantity_reserved' => 0]
        );

        $stock->increment('quantity_on_hand', $quantity);

        $this->transactions->create([
            'branch_id' => $branchId,
            'ingredient_id' => $ingredientId,
            'transaction_type' => $type,
            'quantity' => $quantity,
            'staff_id' => $staffId,
            'notes' => $notes,
        ]);

        $ingredient = Ingredient::find($ingredientId);
        if ($ingredient?->reorder_point && $stock->quantity_on_hand <= $ingredient->reorder_point) {
            Notification::create([
                'tenant_id' => $ingredient->tenant_id,
                'branch_id' => $branchId,
                'type' => 'low_stock',
                'title' => 'Low Stock Alert',
                'body' => "{$ingredient->name} is running low ({$stock->quantity_on_hand} {$ingredient->unit} remaining).",
                'data' => ['ingredient_id' => $ingredientId],
            ]);
        }

        return $stock;
    }
}
