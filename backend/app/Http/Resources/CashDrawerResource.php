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
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'cash_register_id' => $this->cash_register_id,
            'cash_register_name' => $this->whenLoaded('cashRegister', fn () => $this->cashRegister?->name),
            'staff_id' => $this->staff_id,
            // A null staff_id here always means the owner opened/manages
            // this shift themselves (owners have no Staff row at all) —
            // show that explicitly rather than a blank/undefined name.
            'staff_name' => $this->staff_id === null
                ? 'Owner'
                : $this->whenLoaded('staff', fn () => $this->staff?->user
                    ? trim($this->staff->user->first_name.' '.$this->staff->user->last_name)
                    : null),
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
