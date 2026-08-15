<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanBillingCycleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'months' => $this->months,
            'discount_percent' => $this->discount_percent,
            'is_active' => $this->is_active,
        ];
    }
}
