<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

#[ScopedBy(TenantScope::class)]
class Invoice extends Model
{
    use HasUuids;

    protected $fillable = [
        'invoice_number',
        'tenant_id',
        'subscription_id',
        'amount_usd',
        'amount_khr',
        'currency',
        'status',
        'due_date',
        'paid_at',
        'pdf_url',
        'period_start',
        'period_end',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'due_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'amount_usd' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription()
    {
        return $this->belongsTo(
            TenantSubscription::class,
            'subscription_id'
        );
    }

    public function paymentTransactions()
    {
        return $this->hasMany(
            PaymentTransaction::class
        );
    }
}
