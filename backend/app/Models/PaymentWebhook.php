<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PaymentWebhook extends Model
{
    use HasUuids;

    protected $fillable = [
        'gateway',
        'gateway_txn_id',
        'event_type',
        'raw_payload',
        'signature',
        'verified',
        'processed',
        'received_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'verified' => 'boolean',
        'processed' => 'boolean',
        'received_at' => 'datetime',
    ];
}
