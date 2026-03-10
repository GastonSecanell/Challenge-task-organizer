<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;

class Audit
{
    public static function log(?User $user, string $action, ?string $entityType = null, ?int $entityId = null, array $payload = []): void
    {
        AuditLog::query()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => $payload ?: null,
        ]);
    }
}

