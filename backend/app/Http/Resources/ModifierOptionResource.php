<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModifierOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'name' => $this->name,
            'price_adjustment' => $this->price_adjustment,
            'is_available' => $this->is_available,
            'sort_order' => $this->sort_order,
        ];
    }
}
