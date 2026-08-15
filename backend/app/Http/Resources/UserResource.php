<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'phone' => $this->phone,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'avatar_url' => $this->avatar_url,
            'preferred_language' => $this->preferred_language,
            'is_active' => $this->is_active,
            'is_super_admin' => $this->is_super_admin,
            'is_admin' => $this->is_admin,
            'email_verified_at' => $this->email_verified_at,
            'last_login_at' => $this->last_login_at,
            'resolved_type' => $this->resolved_type,
            'created_at' => $this->created_at,
        ];
    }
}
