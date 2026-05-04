<?php

namespace App\Models;

// app/Models/Feature.php
class Feature extends BaseModel
{
    protected $fillable = ['code', 'name', 'description', 'icon', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function businessTypes()
    {
        return $this->belongsToMany(BusinessType::class, 'business_type_features')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function branchTypes()
    {
        return $this->belongsToMany(BranchType::class, 'branch_type_features')
            ->withPivot('is_required', 'is_default')
            ->withTimestamps();
    }
}
