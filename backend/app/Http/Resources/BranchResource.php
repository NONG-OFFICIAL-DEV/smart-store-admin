<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Relation methods here are camelCase (tenant->businessType(),
 * branch()->branchType()), but every frontend consumer reads snake_case
 * (`branch.branch_type`, `branch.tenant.business_type`) — raw Eloquent
 * JSON serialization used to expose the camelCase key instead, so
 * `BranchDetail.vue`'s `tenant.business_type.code` read `undefined.code`
 * and threw every time the branch detail panel opened. Explicit snake_case
 * keys here fix that (and the branch-list card's silently-blank branch
 * type badge, same root cause, no crash there since it's optional-chained).
 */
class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'postal_code' => $this->postal_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'phone' => $this->phone,
            'email' => $this->email,
            'tax_rate' => $this->tax_rate,
            'service_charge_rate' => $this->service_charge_rate,
            'receipt_footer' => $this->receipt_footer,
            'is_open' => $this->is_open,
            'is_active' => $this->is_active,
            'branch_type_id' => $this->branch_type_id,
            'full_address' => $this->full_address,
            'created_at' => $this->created_at?->toIso8601String(),

            'branch_type' => $this->whenLoaded('branchType', fn () => [
                'id' => $this->branchType->id,
                'code' => $this->branchType->code,
                'name' => $this->branchType->name,
                'icon' => $this->branchType->icon,
            ]),

            'tenant' => $this->whenLoaded('tenant', fn () => [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
                'business_type' => $this->tenant->relationLoaded('businessType') && $this->tenant->businessType ? [
                    'id' => $this->tenant->businessType->id,
                    'code' => $this->tenant->businessType->code,
                    'name' => $this->tenant->businessType->name,
                ] : null,
            ]),

            'menus' => $this->whenLoaded('menus', fn () => $this->menus->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
            ])),

            'staff' => $this->whenLoaded('staff', fn () => $this->staff->map(fn ($s) => [
                'id' => $s->id,
                'user' => $s->relationLoaded('user') ? [
                    'first_name' => $s->user?->first_name,
                    'last_name' => $s->user?->last_name,
                ] : null,
                'role' => $s->relationLoaded('role') ? [
                    'name' => $s->role?->name,
                ] : null,
            ])),

            'tables' => $this->whenLoaded('tables', fn () => $this->tables->map(fn ($t) => [
                'id' => $t->id,
                'table_number' => $t->table_number,
                'capacity' => $t->capacity,
                'shape' => $t->shape,
                'status' => $t->status,
            ])),
        ];
    }
}
