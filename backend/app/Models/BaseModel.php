<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

abstract class BaseModel extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected static function boot(): void
    {
        parent::boot();

        // Auto-generate UUID on create
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /**
     * Generic create-or-update (store pattern).
     * Each model overrides this to pass only its own fillable fields.
     */
    public static function store(array $data, ?string $id = null)
    {
        if ($id) {
            $record = static::find($id);
            if (!$record) {
                return response()->json(['error' => 'Record not found'], 404);
            }
            $record->update($data);
            return response()->json(['success' => true, 'data' => $record->fresh()], 200);
        }

        $record = static::create($data);
        return response()->json(['success' => true, 'data' => $record], 201);
    }

    /**
     * Soft-disable a record (set is_active = false).
     */
    public static function remove(string $id)
    {
        $record = static::find($id);
        if (!$record) {
            return response()->json(['error' => 'Record not found'], 404);
        }
        $record->update(['is_active' => false]);
        return response()->json(['success' => true, 'message' => 'Record deactivated'], 200);
    }
}
