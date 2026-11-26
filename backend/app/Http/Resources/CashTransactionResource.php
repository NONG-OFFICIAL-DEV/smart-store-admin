<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cash_drawer_id' => $this->cash_drawer_id,
            'staff_id' => $this->staff_id,
            'type' => $this->type,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
