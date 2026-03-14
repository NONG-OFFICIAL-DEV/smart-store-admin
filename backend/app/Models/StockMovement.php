<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StockMovement extends Model
{
    protected $keyType      = 'string';
    public    $incrementing = false;
    public    $timestamps   = false;
    protected $guarded      = [];

    protected $casts = [
        'quantity'   => 'float',
        'qty_before' => 'float',
        'qty_after'  => 'float',
        'unit_cost'  => 'float',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn($m) => $m->id ??= (string) Str::uuid());
    }

    public function product() { return $this->belongsTo(Product::class); }
    public function branch()  { return $this->belongsTo(Branch::class); }
    public function staff()   { return $this->belongsTo(Staff::class); }
}
