<?php

namespace App\Extensions\TitanNova\System\Support;

use App\Extensions\TitanNova\System\Models\TitanNovaAgent;
use App\Extensions\TitanNova\System\Models\TitanNovaTask;
use Illuminate\Support\Facades\Cache;

class TitanNovaTaskCreationCache
{
    private const CACHE_PREFIX = 'titan-nova:creation:';

    public static function key(int $userId): string { return self::CACHE_PREFIX . $userId; }

    public static function mark(TitanNovaAgent $agent, string $status, array $payload = []): array
    {
        if (! array_key_exists('pending_tasks_count', $payload)) {
            $payload = array_merge(static::computeTaskStats($agent), $payload);
        }
        $data = array_merge($payload, ['agent_id' => $agent->id, 'status' => $status, 'updated_at' => now()->toIso8601String()]);
        Cache::put(self::key($agent->user_id), $data, now()->addHours(6));
        return $data;
    }

    public static function getForUser(int $userId): ?array { return Cache::get(self::key($userId)); }
    public static function forgetForUser(int $userId): void { Cache::forget(self::key($userId)); }

    public static function currentStatus(TitanNovaAgent $agent): array
    {
        $status = self::getForUser($agent->user_id) ?? [
            'agent_id' => $agent->id,
            'status' => data_get($agent->task_creation_status, 'status', 'idle'),
            'updated_at' => data_get($agent->task_creation_status, 'updated_at'),
        ];
        return array_key_exists('pending_tasks_count', $status) ? $status : array_merge($status, static::computeTaskStats($agent));
    }

    /**
     * Legacy method name retained. Returned data includes both task keys and
     * original task keys so untouched TitanNovaAgent UI fragments remain compatible.
     */
    public static function computeTaskStats(TitanNovaAgent $agent): array
    {
        $cached = self::getForUser($agent->user_id);
        $planned = (int) data_get($cached, 'total_requested', data_get($cached, 'planned_tasks_count', 0));
        $agentIds = TitanNovaAgent::query()->where('user_id', $agent->user_id)->pluck('id')->all() ?: [$agent->id];
        $query = TitanNovaTask::query()->whereIn('agent_id', $agentIds);
        $pending = (clone $query)->whereIn('status', [TitanNovaTask::STATUS_DRAFT, TitanNovaTask::STATUS_PENDING_APPROVAL])->count();
        $scheduled = (clone $query)->where('status', TitanNovaTask::STATUS_SCHEDULED)->count();
        $completed = (clone $query)->where('status', TitanNovaTask::STATUS_COMPLETED)->count();
        $total = $query->count();
        $created = (int) data_get($cached, 'created_count', data_get($cached, 'created_count', data_get($agent->task_creation_status, 'created_count', 0)));

        return [
            'pending_tasks_count' => $pending,
            'scheduled_tasks_count' => $scheduled,
            'completed_tasks_count' => $completed,
            'total_tasks_count' => $total,
            'created_tasks_count' => $created,
            'planned_tasks_count' => $planned,
            'pending_tasks_count' => $pending,
            'scheduled_tasks_count' => $scheduled,
            'total_tasks_count' => $total,
            'created_tasks_count' => $created,
            'planned_tasks_count' => $planned,
            'total_requested' => $planned,
        ];
    }
}
