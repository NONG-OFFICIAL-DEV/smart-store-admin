<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'branch_id' => $this->branch_id,
            'staff_id' => $this->staff_id,
            'payment_method' => $this->payment_method,
            'amount' => $this->amount,
            'change_given' => $this->change_given,
            'currency' => $this->currency,
            'exchange_rate' => $this->exchange_rate,
            'status' => $this->status,
            'gateway' => $this->gateway,
            'gateway_transaction_id' => $this->gateway_transaction_id,
            'receipt_number' => $this->receipt_number,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
        ];
    }
}
