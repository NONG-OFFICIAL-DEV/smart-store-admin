<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'order_id' => $this->order_id,
            'staff_id' => $this->staff_id,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'method' => $this->method,
            'status' => $this->status,
            'gateway_refund_id' => $this->gateway_refund_id,
            'created_at' => $this->created_at,
        ];
    }
}
