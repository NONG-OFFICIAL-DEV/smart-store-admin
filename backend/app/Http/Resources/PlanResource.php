<?php

namespace App\Http\Resources;

use App\Services\PlanFeatureListingService;
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
            'trial_days' => $this->trial_days,
            'is_active' => $this->is_active,
            'billing_cycles' => PlanBillingCycleResource::collection($this->whenLoaded('billingCycles')),
            'features' => PlanFeatureResource::collection($this->whenLoaded('features')),
            // Resolved against the live catalog — label text, value type,
            // and this plan's value, pre-joined so no consumer has to
            // guess. Re-queries the catalog per plan; fine at this app's
            // scale (a handful of plans).
            'feature_list' => app(PlanFeatureListingService::class)->resolveForPlan($this->resource),
        ];
    }
}
