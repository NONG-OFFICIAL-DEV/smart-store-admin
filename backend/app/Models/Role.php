<?php

namespace App\Models;

use Illuminate\Http\Request;

class Role extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'name', 'description', 'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    // ─── Store ────────────────────────────────────────────────────────────────
    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only(['tenant_id', 'name', 'description', 'is_system'])
            : $request;

        if ($id) {
            $record = static::find($id);
            if (!$record) {
                return response()->json(['error' => 'Record not found'], 404);
            }
            if ($record->is_system) {
                return response()->json(['error' => 'System roles cannot be modified'], 403);
            }
            $record->update($data);

            // Sync permissions if provided
            if ($request instanceof Request && $request->has('permission_ids')) {
                $record->permissions()->sync($request->permission_ids);
            }

            return response()->json(['success' => true, 'data' => $record->load('permissions')], 200);
        }

        $record = static::create($data);

        if ($request instanceof Request && $request->has('permission_ids')) {
            $record->permissions()->sync($request->permission_ids);
        }

        return response()->json(['success' => true, 'data' => $record->load('permissions')], 201);
    }

    // ─── Relationships ────────────────────────────────────────────────────────
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function staff()
    {
        return $this->hasMany(Staff::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────
    public function hasPermission(string $code): bool
    {
        return $this->permissions->contains('code', $code);
    }
}
