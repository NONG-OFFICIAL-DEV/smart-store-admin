<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'floor_plan_id' => $this->floor_plan_id,
            'table_number' => $this->table_number,
            'capacity' => $this->capacity,
            'shape' => $this->shape,
            'position_x' => $this->position_x,
            'position_y' => $this->position_y,
            'qr_code' => $this->qr_code,
            'qr_image_url' => $this->qr_image_url,
            'status' => $this->status,
            'is_active' => $this->is_active,
        ];
    }
}
