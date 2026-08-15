<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;

#[ScopedBy(TenantScope::class)]
class BranchMenu extends BaseModel
{
    // Lets this be registered via ->using(BranchMenu::class) on the
    // Menu<->Branch belongsToMany (needed so sync()/attach() insert
    // through the model — and thus BaseModel's UUID-generating
    // creating() hook — instead of a raw query missing branch_menus.id).
    // Keeping BaseModel as the parent (not Relations\Pivot) so the UUID
    // hook still applies; AsPivot supplies the rest of what ->using() needs.
    use AsPivot;

    protected $table = 'branch_menus';

    protected $fillable = [
        'branch_id',
        'menu_id',
        'available_from',
        'available_until',
        'days_of_week',
        'sort_order',
    ];

    protected $casts = [
        'days_of_week' => 'array',   // stored as JSON, returned as PHP array
        'sort_order'   => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    // Check if this menu is available right now
    public function isAvailableNow(): bool
    {
        $now     = now();
        $today   = $now->dayOfWeek; // 0 = Sunday, 6 = Saturday
        $nowTime = $now->format('H:i:s');

        // Check day of week
        if ($this->days_of_week && !in_array($today, $this->days_of_week)) {
            return false;
        }

        // Check time window
        if ($this->available_from && $nowTime < $this->available_from) {
            return false;
        }
        if ($this->available_until && $nowTime > $this->available_until) {
            return false;
        }

        return true;
    }
}
