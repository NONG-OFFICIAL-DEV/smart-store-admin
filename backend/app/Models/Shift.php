<?php

namespace App\Models;

use Illuminate\Http\Request;

class Shift extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'shift_type',
        'start_time',
        'end_time',
        'break_minutes',
        'is_active',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'break_minutes'  => 'integer',
    ];

    // ─── Store ────────────────────────────────────────────────────────────────
    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'tenant_id',
                'branch_id',
                'name',
                'shift_type',
                'start_time',
                'end_time',
                'break_minutes',
                'is_active',
            ])
            : $request;

        return parent::store($data, $id);
    }

    // ── Relationships ─────────────────────────────────────────────────────────
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // All assignments using this template
    public function staffShifts()
    {
        return $this->hasMany(StaffShift::class);
    }

    // All staff who have ever been assigned this shift
    public function staff()
    {
        return $this->hasManyThrough(Staff::class, StaffShift::class, 'shift_template_id', 'id', 'id', 'staff_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    // How many hours is this shift (excluding break)
    public function getDurationAttribute(): string
    {
        $start = \Carbon\Carbon::parse($this->start_time);
        $end   = \Carbon\Carbon::parse($this->end_time);

        // Handle overnight shifts (e.g. 22:00 → 06:00)
        if ($end->lessThan($start)) {
            $end->addDay();
        }

        $mins    = $end->diffInMinutes($start) - ($this->break_minutes ?? 0);
        $hours   = intdiv($mins, 60);
        $minutes = $mins % 60;

        return "{$hours}h " . str_pad($minutes, 2, '0', STR_PAD_LEFT) . "m";
    }

    // Is this an overnight shift?
    public function getIsOvernightAttribute(): bool
    {
        return $this->end_time < $this->start_time;
    }
}
