<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailySalesSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'date' => $this->date,
            'total_orders' => $this->total_orders,
            'total_revenue' => $this->total_revenue,
            'total_discount' => $this->total_discount,
            'total_tax' => $this->total_tax,
            'total_tips' => $this->total_tips,
            'net_revenue' => $this->net_revenue,
            'total_cogs' => $this->total_cogs,
            'gross_profit' => $this->gross_profit,
            'avg_order_value' => $this->avg_order_value,
            'dine_in_orders' => $this->dine_in_orders,
            'takeaway_orders' => $this->takeaway_orders,
            'delivery_orders' => $this->delivery_orders,
            'new_customers' => $this->new_customers,
        ];
    }
}
