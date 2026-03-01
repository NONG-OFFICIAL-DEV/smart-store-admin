<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StockService
{

    public const MOVEMENT_TYPES = [
        'purchase',
        'sale',
        'return',
        'adjustment',
        'transfer_in',
        'transfer_out',
        'loss',
    ];
    /**
     * Add stock and record movement
     */
    public static function addStock(int $productId, float $quantity, float $cost = 0, string $movementType = 'purchase', int $relatedId = null, string $note = null, bool $isDraft = false)
    {
        DB::transaction(function () use ($productId, $quantity, $cost, $movementType, $relatedId, $note, $isDraft) {
       
            $stock = Stock::firstOrCreate(['product_id' => $productId]);

            if ($isDraft) {
                // Add to draft_qty only
                $stock->increment('draft_qty', $quantity);
            } else {
                // Add to real stock
                $stock->increment('quantity', $quantity);
                $user = Auth::user();
                // 2. Record stock movement
                StockMovement::create([
                    'product_id' => $productId,
                    'movement_type' => $movementType,
                    'qty' => $quantity,
                    'cost' => $cost,
                    'related_id' => $relatedId,
                    'note' => $note,
                    'created_by' => $user->id,
                ]);
            }
        });
    }

    /**
     * Reduce stock and record movement
     */
    public static function reduceStock(int $productId, float $quantity, float $cost = 0, string $movementType = 'sale', int $relatedId = null, string $note = null)
    {
        DB::transaction(function () use ($productId, $quantity, $cost, $movementType, $relatedId, $note) {
            // 1. Decrement stock
            $stock = Stock::where('product_id', $productId)->firstOrFail();
            $stock->decrement('quantity', $quantity);

            $user = Auth::user();
            // 2. Record stock movement
            StockMovement::create([
                'product_id' => $productId,
                'movement_type' => $movementType,
                'qty' => -abs($quantity), // negative for outflow
                'cost' => $cost,
                'related_id' => $relatedId,
                'note' => $note,
                'created_by' => $user->id,
            ]);
        });
    }

    /**
     * Get stock movements for a product
     */
    public function getMovements(int $productId)
    {
        return StockMovement::with(['product', 'creator'])
            ->where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->get();
    }


    public function returnStock(int $productId, float $qty, string $note = null, int|null $relatedId = null)
    {
        return $this->addStock(
            productId: $productId,
            quantity: $qty,
            movementType: 'return',
            relatedId: $relatedId,
            note: $note
        );
    }

    public function adjustStock(int $productId, float $qty, string $note = null)
    {
        return $this->addStock(
            productId: $productId,
            quantity: $qty,
            movementType: 'adjustment',
            note: $note
        );
    }

    public function reportLoss(int $productId, float $qty, string $note = null)
    {
        return $this->addStock(
            productId: $productId,
            quantity: -abs($qty), // reduce stock
            movementType: 'loss',
            note: $note
        );
    }
}
