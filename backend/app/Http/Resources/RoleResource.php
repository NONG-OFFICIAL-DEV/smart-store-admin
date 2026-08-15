<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'description' => $this->description,
            'is_system' => $this->is_system,
            // Frontend's assignableRoles getter filters on this
            // (r.code !== 'owner') to keep the Owner role out of
            // role-assignment dropdowns — was missing here entirely.
            'code' => $this->code,
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->map(fn ($p) => [
                'id' => $p->id,
                'code' => $p->code,
                'group' => $p->group,
                'description' => $p->description,
            ])),
        ];
    }
}
