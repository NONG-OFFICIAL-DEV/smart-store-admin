<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashDrawerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'staff_id' => $this->staff_id,
            'opening_float' => $this->opening_float,
            'expected_cash' => $this->expected_cash,
            'actual_cash' => $this->actual_cash,
            'variance' => $this->variance,
            'opened_at' => $this->opened_at,
            'closed_at' => $this->closed_at,
            'notes' => $this->notes,
        ];
    }
}
