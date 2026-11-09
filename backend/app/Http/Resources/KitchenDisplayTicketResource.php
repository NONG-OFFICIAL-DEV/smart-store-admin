<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KitchenDisplayTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'branch_id' => $this->branch_id,
            'station' => $this->station,
            'status' => $this->status,
            'priority' => $this->priority,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'prep_time_minutes' => $this->prep_time_minutes,
            'created_at' => $this->created_at,

            // Nested here (see KitchenDisplayTicketRepository::query()'s
            // eager-load) specifically so the frontend never has to follow
            // up with a separate GET /orders/{id} per ticket just to show
            // the order number/status/table/items on each card — that N+1
            // was turning one page load into dozens of requests.
            'order' => $this->whenLoaded('order', function () {
                $order = $this->order;

                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'source' => $order->source,
                    'table' => $order->relationLoaded('diningTable') && $order->diningTable ? [
                        'id' => $order->diningTable->id,
                        'number' => $order->diningTable->table_number,
                    ] : null,
                    'items' => $order->relationLoaded('items') ? $order->items->map(fn ($item) => [
                        'quantity' => $item->quantity,
                        'product_name' => $item->product_name,
                        'variant_name' => $item->variant_name,
                        'notes' => $item->notes,
                        'modifiers' => $item->relationLoaded('modifiers')
                            ? $item->modifiers->map(fn ($m) => ['name' => $m->option_name])->values()
                            : [],
                    ])->values() : [],
                ];
            }),
        ];
    }
}
