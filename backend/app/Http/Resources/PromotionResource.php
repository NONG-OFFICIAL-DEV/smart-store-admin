<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'discount_value' => $this->discount_value,
            'min_order_amount' => $this->min_order_amount,
            'max_discount_amount' => $this->max_discount_amount,
            'applies_to' => $this->applies_to,
            'applicable_ids' => $this->applicable_ids,
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            'usage_limit' => $this->usage_limit,
            'usage_count' => $this->usage_count,
            'per_customer_limit' => $this->per_customer_limit,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
