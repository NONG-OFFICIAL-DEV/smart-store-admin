<?php

namespace App\Models;

use Illuminate\Http\Request;
// ─────────────────────────────────────────────────────────────────────────────
// ModifierOption
// ─────────────────────────────────────────────────────────────────────────────
class ModifierOption extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'group_id',
        'name',
        'price_adjustment',
        'is_available',
        'sort_order',
    ];

    protected $casts = [
        'price_adjustment' => 'decimal:2',
        'is_available'     => 'boolean',
        'sort_order'       => 'integer',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'group_id',
                'name',
                'price_adjustment',
                'is_available',
                'sort_order',
            ])
            : $request;

        return parent::store($data, $id);
    }

    public function group()
    {
        return $this->belongsTo(ModifierGroup::class, 'group_id');
    }
}

