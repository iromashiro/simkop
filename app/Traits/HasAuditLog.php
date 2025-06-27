<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait HasAuditLog
{
    protected static function bootHasAuditLog()
    {
        static::created(function (Model $model) {
            $model->logAudit('CREATE');
        });

        static::updated(function (Model $model) {
            $model->logAudit('UPDATE');
        });

        static::deleted(function (Model $model) {
            $model->logAudit('DELETE');
        });
    }

    public function logAudit(string $action, array $oldValues = null, array $newValues = null): void
    {
        if (!auth()->check()) {
            return;
        }

        $cooperativeId = null;
        if (method_exists($this, 'cooperative')) {
            $cooperativeId = $this->cooperative_id;
        } elseif (auth()->user()->cooperative_id) {
            $cooperativeId = auth()->user()->cooperative_id;
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'cooperative_id' => $cooperativeId,
            'table_name' => $this->getTable(),
            'record_id' => $this->getKey(),
            'action' => $action,
            'old_values' => $oldValues ?? $this->getOriginal(),
            'new_values' => $newValues ?? $this->getAttributes(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
