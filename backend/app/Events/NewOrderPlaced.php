<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Pushed the instant a customer places an order from the public e-menu
 * (mobile/QR) — see OrderController::store(). Branch-scoped so Kitchen
 * Display and Orders Management can show it live and play a sound,
 * without a tenant's dashboard seeing another tenant's orders.
 *
 * ShouldBroadcastNow (not queued) to match NotificationCreated's
 * precedent — a kitchen alert that arrives a queue-cycle late defeats
 * the point.
 */
class NewOrderPlaced implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $branchId,
        public array $order
    ) {
    }

    /**
     * @return array<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("branch.{$this->branchId}.orders")];
    }

    public function broadcastAs(): string
    {
        return 'order.new';
    }

    public function broadcastWith(): array
    {
        return ['order' => $this->order];
    }
}
