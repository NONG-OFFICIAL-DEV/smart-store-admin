<?php

namespace App\Models;

use Illuminate\Http\Request;

class CustomerAddress extends BaseModel
{
    protected $table = 'customer_addresses';

    public $timestamps = false;

    protected $fillable = [
        'customer_id', 'label', 'address_line1', 'address_line2',
        'city', 'state', 'postal_code', 'country',
        'latitude', 'longitude', 'is_default',
    ];

    protected $casts = [
        'latitude'   => 'decimal:6',
        'longitude'  => 'decimal:6',
        'is_default' => 'boolean',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'customer_id', 'label', 'address_line1', 'address_line2',
                'city', 'state', 'postal_code', 'country',
                'latitude', 'longitude', 'is_default',
            ])
            : $request;

        // Only one default address per customer
        if (!empty($data['is_default']) && $data['is_default']) {
            static::where('customer_id', $data['customer_id'])
                  ->update(['is_default' => false]);
        }

        return parent::store($data, $id);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
