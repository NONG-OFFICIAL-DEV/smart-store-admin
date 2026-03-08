<?php

namespace App\Models;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Database\Eloquent\Casts\Attribute;

// ─────────────────────────────────────────────────────────────────────────────
// Table
// ─────────────────────────────────────────────────────────────────────────────
class Table extends BaseModel
{
    protected $table = 'tables';

    public $timestamps = false;

    protected $fillable = [
        'branch_id',
        'floor_plan_id',
        'table_number',
        'capacity',
        'shape',
        'position_x',
        'position_y',
        'qr_code',
        'qr_image_path',
        'status',
        'is_active',
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
                'branch_id',
                'floor_plan_id',
                'table_number',
                'capacity',
                'shape',
                'position_x',
                'position_y',
                'qr_code',
                'status',
                'is_active',
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

    protected function qrImageUrl(): Attribute
    {
        return Attribute::get(
            fn() => $this->qr_image_path
                ? config('app.url') . '/storage/' . $this->qr_image_path
                : null
        );
    }

    // ─── Auto-generate QR on create ───────────────────────────────────────────
    protected static function booted(): void
    {
        static::created(function ($table) {
            $table->generateQrCode();
        });
    }

    // ─── Generate QR ──────────────────────────────────────────────────────────
    public function generateQrCode(): void
    {
        // Load branch if not loaded
        $branch = $this->relationLoaded('branch')
            ? $this->branch
            : $this->load('branch')->branch;

        if (!$branch?->slug) return;

        $url = config('app.frontend_url')
            . '/menu/' . $branch->slug
            . '/table/' . $this->id;

        $qrImage = QrCode::format('svg')
            ->size(400)
            ->margin(2)
            ->errorCorrection('H')
            ->generate($url);

        $path = 'qrcodes/tables/' . $this->id . '.svg';
        Storage::disk('public')->put($path, $qrImage);

        $this->updateQuietly([
            'qr_code'       => $url,
            'qr_image_path' => 'qrcodes/tables/' . $this->id . '.svg'
        ]);
    }
}
