<?php

namespace App\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            static::logAudit($model, 'create', [], $model->getAttributes());
        });

        static::updated(function ($model) {
            $original = $model->getOriginal();
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if (empty($changes)) {
                return;
            }

            $oldData = array_intersect_key($original, $changes);
            static::logAudit($model, 'update', $oldData, $changes);
        });

        static::deleted(function ($model) {
            static::logAudit($model, 'delete', $model->getOriginal(), []);
        });
    }

    protected static function logAudit($model, string $action, array $oldData, array $newData): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'module' => static::auditModule(),
            'action' => $action,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_data' => $oldData ?: null,
            'new_data' => $newData ?: null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }

    protected static function auditModule(): string
    {
        return strtolower(class_basename(static::class));
    }
}
