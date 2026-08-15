<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KitchenDisplayTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'branch_id' => $this->branch_id,
            'station' => $this->station,
            'status' => $this->status,
            'priority' => $this->priority,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'prep_time_minutes' => $this->prep_time_minutes,
            'created_at' => $this->created_at,
        ];
    }
}
