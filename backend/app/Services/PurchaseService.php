<?php

namespace App\Services;

class PurchaseService
{
    /**
     * Calculate totals for purchase items
     *
     * @param array $items
     * @param float $globalDiscountPercent
     * @param float $globalTaxPercent
     * @return array
     */
    public static function calculate(array $items, float $globalDiscountPercent = 0, float $globalTaxPercent = 0)
    {
        $itemTotals = [];
        $totalItemDiscount = 0;
        $totalItemTax = 0;

        foreach ($items as $item) {

            $itemDiscountPercent = $item['item_discount'] ?? 0;
            $itemTaxPercent = $item['item_tax'] ?? 0;

            $itemSubtotal = $item['quantity'] * floatval($item['cost_price']);

            $itemDiscountAmount = $itemSubtotal * ($itemDiscountPercent / 100);
            $itemTaxAmount = ($itemSubtotal - $itemDiscountAmount) * ($itemTaxPercent / 100);

            // accumulate
            $totalItemDiscount += $itemDiscountAmount;
            $totalItemTax += $itemTaxAmount;

            $itemTotals[] = [
                'product_id'        => $item['product_id'] ?? null,
                'quantity'          => $item['quantity'],
                'cost_price'        => $item['cost_price'],
                'subtotal'          => $itemSubtotal,
                'discount_percent'  => $itemDiscountPercent,
                'discount_amount'   => $itemDiscountAmount,
                'tax_percent'       => $itemTaxPercent,
                'tax_amount'        => $itemTaxAmount,
                'total'             => $itemSubtotal - $itemDiscountAmount + $itemTaxAmount,
            ];
        }

        // --- Global Discount ---
        $purchaseSubtotal = array_sum(array_column($itemTotals, 'subtotal'));
        $globalDiscountAmount = $purchaseSubtotal * ($globalDiscountPercent / 100);

        // --- Global Tax ---
        $purchaseAfterDiscount = $purchaseSubtotal - $globalDiscountAmount;
        $globalTaxAmount = $purchaseAfterDiscount * ($globalTaxPercent / 100);

        // Final total
        $finalTotal = $purchaseAfterDiscount + $globalTaxAmount;

        return [
            'items'                 => $itemTotals,
            'purchase_subtotal'     => $purchaseSubtotal,
            'total_item_discount'   => $totalItemDiscount,
            'total_item_tax'        => $totalItemTax,
            'global_discount'       => $globalDiscountAmount,
            'global_tax'            => $globalTaxAmount,
            'final_total'           => $finalTotal,
        ];
    }
}
