<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PinAttempt extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'terminal_id',
        'fail_count',
        'locked_until',
        'last_attempt_at',
    ];

    protected $casts = [
        'fail_count'      => 'integer',
        'locked_until'    => 'datetime',
        'last_attempt_at' => 'datetime',
    ];


    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
