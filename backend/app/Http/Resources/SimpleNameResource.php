<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal {id, name} shape for a lazily/partially-loaded relation shown
 * only as a label (e.g. supplier:id,name, branch:id,name) — avoids a
 * one-off Resource class per relation that only ever needs a name.
 */
class SimpleNameResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
