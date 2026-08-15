<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'branch_id' => $this->branch_id,
            'branch' => new SimpleNameResource($this->whenLoaded('branch')),
            'supplier_id' => $this->supplier_id,
            'supplier' => new SimpleNameResource($this->whenLoaded('supplier')),
            'po_number' => $this->po_number,
            'status' => $this->status,
            'expected_delivery' => $this->expected_delivery,
            'total_amount' => $this->total_amount,
            'notes' => $this->notes,
            'created_by_staff_id' => $this->created_by_staff_id,
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
