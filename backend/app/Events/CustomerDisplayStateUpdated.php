<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Relays the POS's full customer-display state (idle/order/pay_qr/thanks)
 * to a paired device. No persistence — CustomerDisplayController::state()
 * fires this straight off the request body, same ShouldBroadcastNow
 * precedent as NewOrderPlaced, since a stale cart on the customer screen
 * defeats the point.
 */
class CustomerDisplayStateUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $branchId,
        public string $deviceId,
        public array $state
    ) {
    }

    /**
     * @return array<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("branch.{$this->branchId}.terminal.{$this->deviceId}.display")];
    }

    public function broadcastAs(): string
    {
        return 'display.state';
    }

    public function broadcastWith(): array
    {
        return $this->state;
    }
}
