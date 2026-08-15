<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Same flattened shape for every endpoint (index/show/store/update) —
 * previously index() hand-flattened user/role/branch into scalar fields
 * (full_name, role_name, branch_name) while store()/update() returned the
 * nested relations instead, so a newly-created row looked different from
 * every other row in the list until the next refetch. Also restores
 * `salary`, which index() never included even though the frontend reads it
 * as a fallback when `hourly_rate` is null.
 */
class StaffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_code' => $this->employee_code,
            'hire_date' => $this->hire_date?->toDateString(),
            'hourly_rate' => $this->hourly_rate,
            'salary' => $this->salary,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),

            'user_id' => $this->user_id,
            'full_name' => $this->user?->full_name,
            'email' => $this->user?->email,
            'phone' => $this->user?->phone,
            'avatar_url' => $this->user?->avatar_url,

            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch?->name,

            'role_id' => $this->role_id,
            'role_name' => $this->role?->name,
        ];
    }
}
