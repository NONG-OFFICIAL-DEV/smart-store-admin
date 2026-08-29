<?php

namespace App\Models;

use App\Enums\PlanFeatureValueType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catalog of feature rows every Plan references by `key` in its own
 * `plan_features` rows — defined once here (label text, value type) so
 * plans only store their own per-key value, not the label/translation.
 * Global reference data, like BusinessType — no tenant scoping.
 */
class PlanFeatureListing extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['key', 'label_en', 'label_km', 'value_type', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'value_type' => PlanFeatureValueType::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
