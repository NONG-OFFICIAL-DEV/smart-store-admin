<?php

namespace App\Models;

// app/Models/BusinessType.php
class BusinessType extends BaseModel
{
    protected $fillable = ['code', 'name', 'icon', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function branchTypes()
    {
        return $this->hasMany(BranchType::class);
    }

    public function features()
    {
        return $this->belongsToMany(Feature::class, 'business_type_features')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function tenants()
    {
        return $this->hasMany(Tenant::class);
    }
}
