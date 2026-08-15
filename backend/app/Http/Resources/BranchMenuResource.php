<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchMenuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'menu_id' => $this->menu_id,
            'available_from' => $this->available_from,
            'available_until' => $this->available_until,
            'days_of_week' => $this->days_of_week,
            'sort_order' => $this->sort_order,
            'branch' => $this->whenLoaded('branch', fn () => [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ]),
            'menu' => $this->whenLoaded('menu', fn () => [
                'id' => $this->menu->id,
                'name' => $this->menu->name,
            ]),
        ];
    }
}
