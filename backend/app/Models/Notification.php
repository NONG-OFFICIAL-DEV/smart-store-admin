<?php

namespace App\Models;

use Illuminate\Http\Request;

// ══════════════════════════════════════════════════════════════════════════════
// Notification
// ══════════════════════════════════════════════════════════════════════════════

class Notification extends BaseModel
{
    protected $table  = 'notifications';
    const UPDATED_AT  = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'role_id',
        'branch_id',
        'type',
        'title',
        'body',
        'data',
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function role()
    {
        return $this->belongsTo(Role::class);
    }
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }

    public static function store(array|Request $request, $id = null)
    {
        $data = $request->only(['tenant_id', 'user_id', 'role_id', 'branch_id', 'type', 'title', 'body', 'data']);
        if ($id) {
            $record = self::find($id);
            if (!$record) return response()->json(['error' => 'Notification not found'], 404);
            $record->update($data);
        } else {
            $record = self::create($data);
        }
        return response()->json(['success' => true, 'data' => $record->fresh()], $id ? 200 : 201);
    }
}
