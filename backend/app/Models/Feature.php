<?php

namespace App\Models;

use Illuminate\Http\Request;

// app/Models/Feature.php
class Feature extends Model
{
    protected $fillable = ['code', 'name', 'description', 'icon', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function businessTypes(): BelongsToMany
    {
        return $this->belongsToMany(BusinessType::class, 'business_type_features')
                    ->withPivot('is_default')
                    ->withTimestamps();
    }

    public function branchTypes(): BelongsToMany
    {
        return $this->belongsToMany(BranchType::class, 'branch_type_features')
                    ->withPivot('is_required', 'is_default')
                    ->withTimestamps();
    }
}