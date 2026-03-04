<?php

namespace App\Models;

use Illuminate\Http\Request;


// ══════════════════════════════════════════════════════════════════════════════
// CustomerAddress
// ══════════════════════════════════════════════════════════════════════════════

class CustomerAddress extends BaseModel
{
    protected $table      = 'customer_addresses';
    public    $timestamps = false;

    protected $fillable = [
        'customer_id',
        'label',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'is_default',
    ];

    protected $casts = ['is_default' => 'boolean'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public static function store(array|Request $request, $id = null)
    {
        $data = $request->only([
            'customer_id',
            'label',
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'country',
            'latitude',
            'longitude',
            'is_default',
        ]);
        if ($id) {
            $record = self::find($id);
            if (!$record) return response()->json(['error' => 'Address not found'], 404);
            $record->update($data);
        } else {
            // If set as default, clear other defaults for this customer
            if (!empty($data['is_default'])) {
                self::where('customer_id', $data['customer_id'])->update(['is_default' => false]);
            }
            $record = self::create($data);
        }
        return response()->json(['success' => true, 'data' => $record->fresh()], $id ? 200 : 201);
    }
}


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
