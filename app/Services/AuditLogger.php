<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /** @param array<string, mixed> $data */
    public function log(string $action, ?Model $subject = null, array $data = []): AuditLog
    {
        return AuditLog::create([
            'actor_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'data' => $data ?: null,
            'ip_address' => request()->ip(),
        ]);
    }
}
