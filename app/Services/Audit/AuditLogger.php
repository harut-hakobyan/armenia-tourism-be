<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class AuditLogger
{
    /** @param array<string, mixed> $oldValues @param array<string, mixed> $newValues */
    public function record(?User $actor, string $action, Model $subject, array $oldValues = [], array $newValues = [], ?string $ipAddress = null): void
    {
        AuditLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $ipAddress,
        ]);
    }
}
