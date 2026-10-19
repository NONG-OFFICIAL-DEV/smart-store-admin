<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'floor_plan_id' => $this->floor_plan_id,
            'table_number' => $this->table_number,
            'capacity' => $this->capacity,
            'shape' => $this->shape,
            'position_x' => $this->position_x,
            'position_y' => $this->position_y,
            'qr_code' => $this->qr_code,
            'qr_image_url' => $this->qr_image_url,
            'status' => $this->status,
            'is_active' => $this->is_active,
            // Computed from open table_sessions, not the stored `status`
            // column — see TableSessionService. `status` still carries
            // reserved/cleaning/inactive, which have no session behind them.
            // Not whenLoaded(): that helper treats a loaded-but-null relation
            // the same as "not loaded" once you pass it a value closure,
            // which is exactly the case for an unoccupied table.
            'is_occupied' => $this->relationLoaded('openSession') ? $this->openSession !== null : false,
            'open_session' => $this->relationLoaded('openSession') && $this->openSession ? (function () {
                $activeOrders = $this->openSession->orders->filter(fn ($order) => $order->payments->isEmpty());

                return [
                    'id' => $this->openSession->id,
                    'opened_at' => $this->openSession->opened_at,
                    'order_count' => $activeOrders->count(),
                    'total' => (float) $activeOrders->sum('total_amount'),
                ];
            })() : null,
        ];
    }
}
