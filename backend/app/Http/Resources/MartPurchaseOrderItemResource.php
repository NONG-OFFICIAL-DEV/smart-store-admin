<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MartPurchaseOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            // Deliberately not ProductResource here — that resource
            // unconditionally accesses $this->category->id / ->supplier->id
            // with no null check, which would fatal on the partial
            // (id/name/image_url/stock_quantity/unit) load used for this
            // nested line-item display.
            'product' => $this->whenLoaded('product', fn() => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'image_url' => $this->product->image_url,
                'stock_quantity' => $this->product->stock_quantity,
                'unit' => $this->product->unit,
            ]),
            'product_unit_id' => $this->product_unit_id,
            'product_name' => $this->product_name,
            'unit_name' => $this->unit_name,
            'qty_per_base' => $this->qty_per_base,
            'quantity_ordered' => $this->quantity_ordered,
            'quantity_received' => $this->quantity_received,
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->total_cost,
            'received_at' => $this->received_at,
        ];
    }
}
