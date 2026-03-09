<?php

namespace App\Observers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class ActivityLogObserver
{
    public function created(Model $model): void
    {
        ActivityLog::log(
            action:      strtolower(class_basename($model)) . '.created',
            entity:      $model,
            payload:     ['after' => $model->toArray()],
            description: 'Created ' . class_basename($model)
        );
    }

    public function updated(Model $model): void
    {
        ActivityLog::log(
            action:      strtolower(class_basename($model)) . '.updated',
            entity:      $model,
            payload:     [
                'before' => $model->getOriginal(),
                'after'  => $model->getChanges(),
            ],
            description: 'Updated ' . class_basename($model)
        );
    }

    public function deleted(Model $model): void
    {
        ActivityLog::log(
            action:      strtolower(class_basename($model)) . '.deleted',
            entity:      $model,
            payload:     ['before' => $model->toArray()],
            description: 'Deleted ' . class_basename($model)
        );
    }
}
