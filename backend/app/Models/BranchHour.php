<?php

namespace App\Models;

use Illuminate\Http\Request;

class BranchHour extends BaseModel
{
    protected $table = 'branch_hours';

    public $timestamps = false;

    protected $fillable = [
        'branch_id', 'day_of_week', 'open_time', 'close_time', 'is_closed',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_closed'   => 'boolean',
    ];

    const DAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    // ─── Store ────────────────────────────────────────────────────────────────
    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only(['branch_id', 'day_of_week', 'open_time', 'close_time', 'is_closed'])
            : $request;

        return parent::store($data, $id);
    }

    // ─── Relationships ────────────────────────────────────────────────────────
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────
    public function getDayNameAttribute(): string
    {
        return self::DAYS[$this->day_of_week] ?? 'Unknown';
    }
}
