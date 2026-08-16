<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'tenant_id' => $this->tenant_id,
            'subscription_id' => $this->subscription_id,
            'amount_usd' => $this->amount_usd,
            'amount_khr' => $this->amount_khr,
            'currency' => $this->currency,
            'status' => $this->status,
            'due_date' => $this->due_date,
            'paid_at' => $this->paid_at,
            'note' => $this->note,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'created_at' => $this->created_at,
        ];
    }
}
