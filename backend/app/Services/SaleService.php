<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\Order;
use App\Models\Sale;
use Illuminate\Support\Facades\Auth;

class SaleService
{
    public function createFromOrder(Order $order): Sale
    {
        // Prevent duplicate sale
        if ($order->sale) {
            return $order->sale;
        }

        $total = $order->items->sum(fn($i) => $i->quantity * $i->price);
        $user = Auth::user();
        $sale = Sale::create([
            'sale_date'      => now(),
            'invoice_no'     => 'SAL-' . now()->format('YmdHis'),
            'order_id'       => $order->id,

            'total_amount'   => $total,
            'discount'       => 0,
            'tax_amount'     => 0,

            'status'         => 'paid',
            'cashier_id'     => $user->id ?? null,
            'notes'          => null,
        ]);


        foreach ($order->items as $item) {
            $price = $item->price ?? $item->menu->price;

            $sale->items()->create([
                'sellable_type' => Menu::class,
                'sellable_id' => $item->menu_id,
                'quantity' => $item->quantity,
                'price' => $price,
                'total' => $item->quantity * $item->price,
            ]);
        }

        // 🔒 Lock order
        $order->update(['status' => 'paid']);
         // ✅ Release table
        if ($order->table) {
            $order->table->update([
                'status'            => 'available',
                'current_order_id'  => null,
                'reserved_at'       => null,
                'reserved_until'    => null,
            ]);
        }

        return $sale;
    }
}
