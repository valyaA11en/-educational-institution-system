<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditService
{
    public static function log(
        string $action,
        string $entity,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null,
        ?int $userId = null,
        ?string $ip = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'before_json' => $before,
            'after_json' => $after,
            'ip' => $ip ?? request()->ip(),
        ]);
    }
}

