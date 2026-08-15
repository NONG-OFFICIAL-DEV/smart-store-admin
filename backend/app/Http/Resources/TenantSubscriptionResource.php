<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'tenant' => $this->whenLoaded('tenant', fn () => [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
                'slug' => $this->tenant->slug,
            ]),
            'plan_id' => $this->plan_id,
            'plan' => $this->whenLoaded('plan', fn () => [
                'id' => $this->plan->id,
                'name' => $this->plan->name,
                'code' => $this->plan->code,
                'price_usd' => $this->plan->price_usd,
            ]),
            'billing_cycle' => $this->whenLoaded('billingCycle', fn () => $this->billingCycle ? [
                'id' => $this->billingCycle->id,
                'label' => $this->billingCycle->label,
                'months' => $this->billingCycle->months,
                'discount_percent' => $this->billingCycle->discount_percent,
            ] : null),
            'status' => $this->status,
            'trial_ends_at' => $this->trial_ends_at,
            'current_period_start' => $this->current_period_start,
            'current_period_end' => $this->current_period_end,
            'cancelled_at' => $this->cancelled_at,
            'created_at' => $this->created_at,
        ];
    }
}
