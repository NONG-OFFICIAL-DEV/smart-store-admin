<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoyaltyRewardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_km' => $this->name_km,
            'description' => $this->description,
            'description_km' => $this->description_km,
            'image_url' => $this->image_url,
            'points_cost' => $this->points_cost,
            'type' => $this->type,
            'discount_value' => $this->discount_value,
            'free_product_id' => $this->free_product_id,
            'free_product' => $this->whenLoaded('freeProduct', fn () => $this->freeProduct ? [
                'id' => $this->freeProduct->id,
                'name' => $this->freeProduct->name,
            ] : null),
            'valid_from' => $this->valid_from,
            'valid_until' => $this->valid_until,
            'is_active' => $this->is_active,
            'branch_ids' => $this->whenLoaded('branches', fn () => $this->branches->pluck('id')),
            'branches' => $this->whenLoaded('branches', fn () => $this->branches->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
