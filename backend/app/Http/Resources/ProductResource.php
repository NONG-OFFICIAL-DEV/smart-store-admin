<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'price' => $this->price,
            'status' => $this->status,
            'category_id' => $this->category->id,
            'supplier_id' => $this->supplier->id,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null,
            'unit_id' => $this->unit->id,
            'unit' => $this->unit ? [
                'id' => $this->unit->id,
                'name' => $this->unit->name,
                'abbreviation' => $this->unit->abbreviation,
            ] : null,
            'image_url' => $this->image_url, // full URL from model accessor
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
