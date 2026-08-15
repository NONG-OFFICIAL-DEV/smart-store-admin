<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'price_usd' => $this->price_usd,
            'price_khr' => $this->price_khr,
            'seats' => $this->seats,
            'storage_gb' => $this->storage_gb,
            'api_limit' => $this->api_limit,
            'is_active' => $this->is_active,
            'billing_cycles' => PlanBillingCycleResource::collection($this->whenLoaded('billingCycles')),
            'features' => PlanFeatureResource::collection($this->whenLoaded('features')),
        ];
    }
}
