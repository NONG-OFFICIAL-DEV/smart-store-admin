<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

use Illuminate\Database\Eloquent\Model;

#[ScopedBy(TenantScope::class)]
class ActivityLog extends BaseModel
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

        // User has no tenant_id/branch_id attribute of its own (it spans
        // every tenant) — resolve the same way TenantScope does: owners via
        // ownedTenant, staff via their Staff record. Getting this wrong
        // previously meant every single row here was written with
        // tenant_id NULL, which TenantScope treats as "shared across every
        // tenant" (same convention as nullable roles.tenant_id) — so every
        // tenant's activity log was silently visible to every other tenant.
        static::create([
            'tenant_id'   => $user?->ownedTenant?->id ?? $user?->staff?->tenant_id,
            'branch_id'   => $user?->staff?->branch_id,
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
