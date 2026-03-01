<?php

namespace App\Models;

use Illuminate\Http\Request;

class Permission extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'code', 'group', 'description',
    ];

    // ─── Store ────────────────────────────────────────────────────────────────
    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only(['code', 'group', 'description'])
            : $request;

        return parent::store($data, $id);
    }

    // ─── Relationships ────────────────────────────────────────────────────────
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
