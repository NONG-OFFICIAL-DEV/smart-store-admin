<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngredientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'category' => $this->category,
            'unit' => $this->unit,
            'unit_cost' => $this->unit_cost,
            'reorder_point' => $this->reorder_point,
            'reorder_quantity' => $this->reorder_quantity,
            'preferred_supplier_id' => $this->preferred_supplier_id,
            'preferred_supplier' => $this->whenLoaded('preferredSupplier', fn () => [
                'id' => $this->preferredSupplier->id,
                'name' => $this->preferredSupplier->name,
            ]),
            'barcode' => $this->barcode,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
