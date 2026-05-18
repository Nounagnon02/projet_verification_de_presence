<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    protected static function bootAuditable()
    {
        static::created(function ($model) {
            $model->auditAction('created');
        });

        static::updated(function ($model) {
            $model->auditAction('updated');
        });

        static::deleted(function ($model) {
            $model->auditAction('deleted');
        });
    }

    protected function auditAction($action)
    {
        AuditLog::create([
            'action' => $action,
            'model_type' => get_class($this),
            'model_id' => $this->id,
            'user_id' => Auth::id(),
            'old_values' => $action === 'updated' ? $this->getOriginal() : null,
            'new_values' => $action !== 'deleted' ? $this->getAttributes() : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent()
        ]);
    }
}