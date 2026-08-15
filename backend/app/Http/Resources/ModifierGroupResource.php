<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModifierGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'selection_type' => $this->selection_type,
            'min_selections' => $this->min_selections,
            'max_selections' => $this->max_selections,
            'is_required' => $this->is_required,
            'options' => $this->whenLoaded('options', fn () => $this->options->map(fn ($o) => [
                'id' => $o->id,
                'name' => $o->name,
                'price_adjustment' => $o->price_adjustment,
                'is_available' => $o->is_available,
                'sort_order' => $o->sort_order,
            ])),
        ];
    }
}
