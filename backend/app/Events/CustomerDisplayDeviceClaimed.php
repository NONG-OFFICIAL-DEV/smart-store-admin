<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells an unpaired device (sitting on the pairing screen, listening on a
 * PUBLIC channel named after its own self-generated device_session_id —
 * no auth needed since that id is a UUID nobody else knows) that a cashier
 * just claimed its code. The device never polls; it just gets pushed its
 * own device_token + branch_id/terminal_id and switches straight to
 * connecting the real private display channel.
 */
class CustomerDisplayDeviceClaimed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $deviceSessionId,
        public string $branchId,
        public string $deviceId,
        public string $deviceToken
    ) {
    }

    /**
     * @return array<Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel("customer-display-pairing.{$this->deviceSessionId}")];
    }

    public function broadcastAs(): string
    {
        return 'device.claimed';
    }

    public function broadcastWith(): array
    {
        return [
            'branch_id' => $this->branchId,
            'terminal_id' => $this->deviceId,
            'device_token' => $this->deviceToken,
        ];
    }
}
