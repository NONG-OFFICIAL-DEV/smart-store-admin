<?php

namespace App\Models;

// ══════════════════════════════════════════════════════════════════════════════
// StaffShift — one staff assigned to one shift on one specific date
// ══════════════════════════════════════════════════════════════════════════════
class StaffShift extends BaseModel
{
    public $timestamps = false; // only has created_at

    protected $fillable = [
        'shift_id',
        'staff_id',
        'branch_id',
        'shift_date',
        'actual_start',
        'actual_end',
        'notes',
    ];

    protected $casts = [
        'shift_date'   => 'date',
        'actual_start' => 'datetime',
        'actual_end'   => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    // Clock in
    public function clockIn(): void
    {
        $this->update(['actual_start' => now()]);
    }

    // Clock out
    public function clockOut(): void
    {
        $this->update(['actual_end' => now()]);
    }

    // Status: scheduled | clocked_in | completed | absent
    public function getStatusAttribute(): string
    {
        if ($this->actual_end)   return 'completed';
        if ($this->actual_start) return 'clocked_in';

        // Shift should have started but no clock-in
        $scheduledStart = \Carbon\Carbon::parse(
            $this->shift_date->toDateString() . ' ' . $this->shiftTemplate->start_time
        );
        if (now()->greaterThan($scheduledStart)) return 'absent';

        return 'scheduled';
    }

    // Actual working minutes (for payroll)
    public function getActualMinutesAttribute(): ?int
    {
        if (!$this->actual_start || !$this->actual_end) return null;
        $gross = $this->actual_end->diffInMinutes($this->actual_start);
        return $gross - ($this->shiftTemplate->break_minutes ?? 0);
    }

    // Scheduled start as full datetime (date + template start_time)
    public function getScheduledStartAttribute(): \Carbon\Carbon
    {
        return \Carbon\Carbon::parse(
            $this->shift_date->toDateString() . ' ' . $this->shiftTemplate->start_time
        );
    }

    // Scheduled end as full datetime (handles overnight)
    public function getScheduledEndAttribute(): \Carbon\Carbon
    {
        $end = \Carbon\Carbon::parse(
            $this->shift_date->toDateString() . ' ' . $this->shiftTemplate->end_time
        );

        // If overnight shift (end time < start time), end is next day
        if ($this->shiftTemplate->is_overnight) {
            $end->addDay();
        }

        return $end;
    }
}
