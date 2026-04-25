<?php

namespace App\Models;

use Illuminate\Http\Request;


// ══════════════════════════════════════════════════════════════════════════════
// BranchProductOverride
// ══════════════════════════════════════════════════════════════════════════════

class BranchProductOverride extends BaseModel
{
    protected $table      = 'branch_product_overrides';
    public    $timestamps = false;

    protected $fillable = ['branch_id', 'product_id', 'override_price', 'is_available'];
    protected $casts    = ['override_price' => 'decimal:2', 'is_available' => 'boolean'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public static function store(array|Request $request, $id = null)
    {
        $data = $request->only(['branch_id', 'product_id', 'override_price', 'is_available']);
        $record = self::updateOrCreate(
            ['branch_id' => $data['branch_id'], 'product_id' => $data['product_id']],
            $data
        );
        return response()->json(['success' => true, 'data' => $record->fresh()], 201);
    }
}
