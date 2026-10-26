<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'name_km' => $this->name_km,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'icon' => $this->icon,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'show_on_pos' => $this->show_on_pos,
            'show_on_emenu' => $this->show_on_emenu,
            'is_lid_exchange' => $this->is_lid_exchange,
            'is_system' => $this->is_system,
            'products_count' => $this->whenCounted('products'),
            'tenants' => $this->whenLoaded('tenants', fn () => $this->tenants->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
            ])),
            'business_types' => $this->whenLoaded('businessTypes', fn () => $this->businessTypes->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
            ])),
        ];
    }
}
