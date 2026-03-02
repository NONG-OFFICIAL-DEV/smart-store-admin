<?php

namespace App\Models;

use Illuminate\Http\Request;

// ─────────────────────────────────────────────────────────────────────────────
// FloorPlan
// ─────────────────────────────────────────────────────────────────────────────
class FloorPlan extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'branch_id', 'name', 'sort_order', 'layout_json',
    ];

    protected $casts = [
        'layout_json' => 'array',
        'sort_order'  => 'integer',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only(['branch_id', 'name', 'sort_order', 'layout_json'])
            : $request;

        return parent::store($data, $id);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function tables()
    {
        return $this->hasMany(Table::class);
    }
}




