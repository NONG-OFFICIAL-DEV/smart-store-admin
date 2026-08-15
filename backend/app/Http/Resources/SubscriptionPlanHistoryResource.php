<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'from_plan' => $this->whenLoaded('fromPlan', fn () => $this->fromPlan ? [
                'id' => $this->fromPlan->id,
                'name' => $this->fromPlan->name,
                'code' => $this->fromPlan->code,
            ] : null),
            'to_plan' => $this->whenLoaded('toPlan', fn () => [
                'id' => $this->toPlan->id,
                'name' => $this->toPlan->name,
                'code' => $this->toPlan->code,
            ]),
            'billing_cycle' => $this->whenLoaded('billingCycle', fn () => $this->billingCycle ? [
                'id' => $this->billingCycle->id,
                'label' => $this->billingCycle->label,
                'months' => $this->billingCycle->months,
            ] : null),
            'changed_by' => $this->whenLoaded('changedByUser', fn () => $this->changedByUser ? [
                'id' => $this->changedByUser->id,
                'name' => trim("{$this->changedByUser->first_name} {$this->changedByUser->last_name}"),
            ] : null),
            'reason' => $this->reason,
            'changed_at' => $this->changed_at,
        ];
    }
}
