<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id',
        'action',
        // 'action_type',
        // 'module',
        'entity_type',
        'entity_id',
        // 'old_values',
        // 'new_values',
        // 'url',
        // 'method',
        'ip_address',
        'payload',
        'user_agent'
    ];

    // protected $casts = [
    //     'old_values' => 'array',
    //     'new_values' => 'array',
    // ];

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
