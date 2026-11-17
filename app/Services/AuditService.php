<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class AuditService
{
    /** @param array<string, mixed> $metadata */
    public static function record(string $event, ?User $actor = null, array $metadata = []): AuditLog
    {
        $allowed = ['reason', 'role', 'roles', 'previous_roles', 'target_user_id', 'channel', 'auth_version', 'status', 'dataset_id', 'version_id', 'source_id', 'sync_run_id', 'rule_id', 'scenario_id', 'article_id', 'comment_id', 'media_id', 'revision', 'count', 'setting'];
        $safe = [];

        foreach (array_intersect_key($metadata, array_flip($allowed)) as $key => $value) {
            if (is_string($value)) {
                $safe[$key] = mb_substr($value, 0, 120);
            } elseif (is_int($value) || is_bool($value) || $value === null) {
                $safe[$key] = $value;
            } elseif (in_array($key, ['roles', 'previous_roles'], true) && is_array($value)) {
                $safe[$key] = array_values(array_filter($value, fn (mixed $role): bool => is_string($role) && preg_match('/^[a-z_]{1,64}$/', $role) === 1));
            }
        }

        return AuditLog::create([
            'actor_id' => $actor?->id,
            'event' => $event,
            'metadata' => $safe,
            'created_at' => now(),
        ]);
    }
}
