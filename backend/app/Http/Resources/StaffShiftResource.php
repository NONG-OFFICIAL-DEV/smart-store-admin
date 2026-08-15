<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shift_id' => $this->shift_id,
            'staff_id' => $this->staff_id,
            'branch_id' => $this->branch_id,
            'shift_date' => $this->shift_date?->toDateString(),
            'actual_start' => $this->actual_start?->toIso8601String(),
            'actual_end' => $this->actual_end?->toIso8601String(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),

            'shift' => $this->whenLoaded('shift', fn () => [
                'id' => $this->shift->id,
                'name' => $this->shift->name,
                'shift_type' => $this->shift->shift_type,
                'start_time' => $this->shift->start_time,
                'end_time' => $this->shift->end_time,
            ]),
            'staff' => $this->whenLoaded('staff', fn () => [
                'id' => $this->staff->id,
                'employee_code' => $this->staff->employee_code,
                'user' => $this->staff->relationLoaded('user') ? [
                    'full_name' => $this->staff->user?->full_name,
                ] : null,
            ]),
            'branch' => $this->whenLoaded('branch', fn () => [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ]),
        ];
    }
}
