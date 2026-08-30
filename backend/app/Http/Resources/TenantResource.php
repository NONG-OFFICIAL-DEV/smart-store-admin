<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * List-view shape only — trims what TenantController::index() returns to
 * exactly what TenantView.vue's table reads. The detail page has its own
 * richer TenantService::detail() shape (invoices, plan history, etc.);
 * this Resource is not meant to serve that page.
 */
class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_url' => $this->logo_url,
            'primary_color' => $this->primary_color,
            'currency' => $this->currency,
            'is_active' => $this->is_active,
            'owner' => $this->whenLoaded('owner', fn () => [
                'first_name' => $this->owner?->first_name,
                'last_name' => $this->owner?->last_name,
                'email' => $this->owner?->email,
            ]),
            'business_type' => $this->whenLoaded('businessType', fn () => [
                'name' => $this->businessType?->name,
                'icon' => $this->businessType?->icon,
            ]),
            'active_subscription' => $this->whenLoaded('activeSubscription', fn () => $this->activeSubscription ? [
                'id' => $this->activeSubscription->id,
                'status' => $this->activeSubscription->status,
                'plan' => [
                    'name' => $this->activeSubscription->plan?->name,
                    'code' => $this->activeSubscription->plan?->code,
                ],
            ] : null),
        ];
    }
}
