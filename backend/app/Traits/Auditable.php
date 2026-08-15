<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
trait Auditable
{
    public static function bootAuditable()
    {
        static::creating(fn($model) => $model->storeAudit('create'));
        static::updating(fn($model) => $model->storeAudit('update'));
        static::deleting(fn($model) => $model->storeAudit('delete'));
    }

    protected function storeAudit(string $action)
    {
        if (static::class === AuditLog::class) return;

        $user = Auth::user();

        $old = $this->maskSensitive($this->getOriginal());
        $new = $this->maskSensitive($this->getAttributes());

        AuditLog::create([
            'user_id' => $user?->id,
            'action_type' => $action,
            'module' => class_basename($this),
            'description' => "{$action} " . class_basename($this),
            'old_values' => $old,
            'new_values' => $new,
            'url' => request()?->fullUrl(),
            'method' => request()?->method(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    protected function maskSensitive(array $data): array
    {
        $sensitive = ['password', 'token', 'remember_token'];
        foreach ($sensitive as $key) {
            if (isset($data[$key])) $data[$key] = '***masked***';
        }
        array_walk_recursive($data, fn(&$v) => $v = is_string($v) && strlen($v) > 1000 ? Str::limit($v, 1000, '...[truncated]') : $v);
        return $data;
    }
}
