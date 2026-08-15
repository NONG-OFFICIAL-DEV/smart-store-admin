<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoyaltyTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'order_id' => $this->order_id,
            'type' => $this->type,
            'points' => $this->points,
            'balance_after' => $this->balance_after,
            'description' => $this->description,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
        ];
    }
}
