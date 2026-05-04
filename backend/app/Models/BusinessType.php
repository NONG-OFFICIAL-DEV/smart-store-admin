<?php

namespace App\Models;

use Illuminate\Http\Request;

// app/Models/BusinessType.php
class BusinessType extends Model
{
    protected $fillable = ['code', 'name', 'icon', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function branchTypes(): HasMany
    {
        return $this->hasMany(BranchType::class);
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'business_type_features')
                    ->withPivot('is_default')
                    ->withTimestamps();
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }
}