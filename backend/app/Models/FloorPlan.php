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


// ─────────────────────────────────────────────────────────────────────────────
// Table
// ─────────────────────────────────────────────────────────────────────────────
class Table extends BaseModel
{
    protected $table = 'tables';

    public $timestamps = false;

    protected $fillable = [
        'branch_id', 'floor_plan_id', 'table_number',
        'capacity', 'shape', 'position_x', 'position_y',
        'qr_code', 'status', 'is_active',
    ];

    protected $casts = [
        'capacity'   => 'integer',
        'position_x' => 'integer',
        'position_y' => 'integer',
        'is_active'  => 'boolean',
    ];

    const STATUS_AVAILABLE = 'available';
    const STATUS_OCCUPIED  = 'occupied';
    const STATUS_RESERVED  = 'reserved';
    const STATUS_CLEANING  = 'cleaning';

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'branch_id', 'floor_plan_id', 'table_number',
                'capacity', 'shape', 'position_x', 'position_y',
                'qr_code', 'status', 'is_active',
            ])
            : $request;

        return parent::store($data, $id);
    }

    // ─── Quick status update ──────────────────────────────────────────────────
    public static function updateStatus(string $id, string $status)
    {
        $table = static::find($id);
        if (!$table) {
            return response()->json(['error' => 'Table not found'], 404);
        }
        $table->update(['status' => $status]);
        return response()->json(['success' => true, 'data' => $table], 200);
    }

    // ─── Relationships ────────────────────────────────────────────────────────
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function floorPlan()
    {
        return $this->belongsTo(FloorPlan::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function activeOrder()
    {
        return $this->hasOne(Order::class)
                    ->whereNotIn('status', ['completed', 'cancelled', 'refunded'])
                    ->latest();
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Reservation
// ─────────────────────────────────────────────────────────────────────────────
class Reservation extends BaseModel
{
    protected $fillable = [
        'branch_id', 'table_id', 'customer_id',
        'customer_name', 'customer_phone', 'party_size',
        'reserved_at', 'duration_minutes', 'status', 'notes',
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
                'branch_id', 'table_id', 'customer_id',
                'customer_name', 'customer_phone', 'party_size',
                'reserved_at', 'duration_minutes', 'status', 'notes',
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
