<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

#[ScopedBy(TenantScope::class)]
class CustomerDisplayDevice extends BaseModel
{
    protected $fillable = [
        'branch_id',
        'device_session_id',
        'pairing_code',
        'code_expires_at',
        'device_token',
        'device_name',
        'paired_at',
        'last_seen_at',
    ];

    protected $casts = [
        'code_expires_at' => 'datetime',
        'paired_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
