<?php

namespace App\Models;

use Illuminate\Http\Request;

// ─────────────────────────────────────────────────────────────────────────────
// Reservation
// ─────────────────────────────────────────────────────────────────────────────
class Reservation extends BaseModel
{
    protected $fillable = [
        'branch_id',
        'table_id',
        'customer_id',
        'customer_name',
        'customer_phone',
        'party_size',
        'reserved_at',
        'duration_minutes',
        'status',
        'notes',
    ];

    protected $casts = [
        'reserved_at'      => 'datetime',
        'party_size'       => 'integer',
        'duration_minutes' => 'integer',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'branch_id',
                'table_id',
                'customer_id',
                'customer_name',
                'customer_phone',
                'party_size',
                'reserved_at',
                'duration_minutes',
                'status',
                'notes',
            ])
            : $request;

        return parent::store($data, $id);
    }

    // ─── Status transitions ───────────────────────────────────────────────────
    public static function confirm(string $id)
    {
        $record = static::find($id);
        if (!$record) return response()->json(['error' => 'Not found'], 404);
        $record->update(['status' => 'confirmed']);
        return response()->json(['success' => true, 'data' => $record], 200);
    }

    public static function seat(string $id, string $tableId)
    {
        $record = static::find($id);
        if (!$record) return response()->json(['error' => 'Not found'], 404);
        $record->update(['status' => 'seated', 'table_id' => $tableId]);
        Table::updateStatus($tableId, Table::STATUS_OCCUPIED);
        return response()->json(['success' => true, 'data' => $record], 200);
    }

    // ─── Relationships ────────────────────────────────────────────────────────
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
