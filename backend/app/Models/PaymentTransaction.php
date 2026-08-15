<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

#[ScopedBy(TenantScope::class)]
class PaymentTransaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'invoice_id',
        'tenant_id',
        'gateway',
        'gateway_txn_id',
        'qr_string',
        'qr_image_url',
        'amount_usd',
        'amount_khr',
        'currency',
        'status',
        'qr_expires_at',
        'paid_at',
        'webhook_payload',
    ];

    protected $casts = [
        'webhook_payload' => 'array',
        'paid_at' => 'datetime',
        'qr_expires_at' => 'datetime',
        'amount_usd' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
