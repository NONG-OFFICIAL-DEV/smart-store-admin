<?php

namespace App\Models;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
// ─────────────────────────────────────────────────────────────────────────────
// ModifierGroup
// ─────────────────────────────────────────────────────────────────────────────
class ModifierGroup extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'name',
        'selection_type',
        'min_selections',
        'max_selections',
        'is_required',
    ];

    protected $casts = [
        'is_required'     => 'boolean',
        'min_selections'  => 'integer',
        'max_selections'  => 'integer',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'tenant_id',
                'name',
                'selection_type',
                'min_selections',
                'max_selections',
                'is_required',
            ])
            : $request;

        // ✅ Inject tenant_id server-side — remove 'tenant_id' from ->only() above
        /** @var \App\Models\User $user */
        $data['tenant_id'] = "52ae3db8-d6da-4ecb-b572-35113d50c23e";
        // Auth::user()->tenant_id;
        return parent::store($data, $id);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function options()
    {
        return $this->hasMany(ModifierOption::class, 'group_id')->orderBy('sort_order');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_modifier_groups')
            ->withPivot('sort_order');
    }
}
