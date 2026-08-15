<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class OrderItemsAdded implements ShouldBroadcast
{
    use SerializesModels;

    public array $orderData;

    public function __construct(Order $order)
    {
        $order->load([
            'table:id,table_number',
            'items:id,order_id,menu_id,quantity,price,note,kitchen_status',
            'items.menu:id,name,image'
        ]);

        $this->orderData = [
            'order_id'   => $order->id,
            'order_no'   => $order->order_no,
            'table'      => $order->table?->table_number,
            'status'     => $order->status,
            'item_count' => $order->items->sum('quantity'),
            'total'      => $order->items->sum(fn($i) => $i->quantity * $i->price),
            'created_at' => $order->created_at,
            'items'      => $order->items->map(fn($item) => [
                'id'             => $item->id,
                'menu_id'        => $item->menu_id,
                'menu_name'      => $item->menu->name,
                'image_url'      => $item->menu->image_url ?? null,
                'qty'            => $item->quantity,
                'price'          => $item->price,
                'note'           => $item->note,
                'kitchen_status' => $item->kitchen_status,
            ]),
        ];
    }

    public function broadcastOn(): array
    {
        return [new Channel('orders')];
    }

    public function broadcastAs(): string
    {
        return 'order.items_added';
    }

    public function broadcastWith(): array
    {
        return $this->orderData;
    }
}
