<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $timestamps = false;
    protected $table = 'activity_logs';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'user_id',
        'user_name',
        'user_email',
        'action',
        'entity_type',
        'entity_id',
        'description',
        'ip_address',
        'user_agent',
        'payload',
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

     // ── Helper to log easily from anywhere ────────────────────────────────────
    public static function log(
        string  $action,
        ?object $entity      = null,
        ?array  $payload     = null,
        ?string $description = null
    ): void {
        $user    = auth()->user();
        $request = request();

        static::create([
            'tenant_id'   => $user?->tenant_id ?? null,
            'branch_id'   => $user?->branch_id ?? null,
            'user_id'     => $user?->id,
            'user_name'   => $user ? trim($user->first_name . ' ' . $user->last_name) : null,
            'user_email'  => $user?->email,
            'action'      => $action,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id'   => $entity?->id,
            'description' => $description,
            'payload'     => $payload,
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);
    }
}
