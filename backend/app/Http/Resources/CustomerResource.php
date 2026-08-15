<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender,
            'avatar_url' => $this->avatar_url,
            'notes' => $this->notes,
            'total_spent' => $this->total_spent,
            'total_orders' => $this->total_orders,
            'loyalty_points' => $this->loyalty_points,
            'loyalty_tier' => $this->loyalty_tier,
            'marketing_opt_in' => $this->marketing_opt_in,
            'preferred_language' => $this->preferred_language,
            'source' => $this->source,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
