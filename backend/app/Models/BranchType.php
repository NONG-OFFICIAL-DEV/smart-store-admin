<?php

namespace App\Models;

use Illuminate\Http\Request;

// app/Models/BranchType.php
class BranchType extends Model
{
    protected $fillable = ['business_type_id', 'code', 'name', 'icon', 'is_hq', 'is_active', 'sort_order'];

    protected $casts = [
        'is_hq'     => 'boolean',
        'is_active' => 'boolean',
    ];

    public function businessType()
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function features()
    {
        return $this->belongsToMany(Feature::class, 'branch_type_features')
                    ->withPivot('is_required', 'is_default')
                    ->withTimestamps();
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }
}