<?php

namespace App\Extensions\TitanNova\System\Console\Commands;

use App\Extensions\TitanNova\System\Models\TitanNovaAgent;
use App\Extensions\TitanNova\System\Models\TitanNovaTask;
use App\Extensions\TitanNova\System\Services\TaskCreationService;
use App\Extensions\TitanNova\System\Support\TitanNovaTaskCreationCache;
use App\Helpers\Classes\Helper;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateAgentTasksCommand extends Command
{
    protected $signature = 'titan-nova:create-tasks
                            {--agent= : Create tasks for a specific agent ID}
                            {--force : Create tasks even when the planning horizon is already covered}';

    protected $aliases = ['titan-nova:create-tasks'];
    protected $description = 'Create and schedule Titan Nova tasks for active TitanNovaAgent agents';

    public function __construct(protected TaskCreationService $taskService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (Helper::appIsDemo()) {
            return self::FAILURE;
        }

        $this->info('Starting Titan Nova task creation...');
        $agents = $this->getAgents($this->option('agent'));
        if ($agents->isEmpty()) {
            $this->warn('No active agents found.');
            return self::SUCCESS;
        }

        $totalCreated = 0;
        foreach ($agents as $agent) {
            try {
                $created = $this->processAgent($agent, (bool) $this->option('force'));
                $totalCreated += $created;
                $this->line($created > 0
                    ? "  ✓ Created {$created} tasks for {$agent->name}"
                    : "  • {$agent->name}'s task horizon is already covered");
            } catch (Exception $exception) {
                $this->error("  ✗ {$agent->name}: {$exception->getMessage()}");
                Log::error('Titan Nova task creation failed', ['agent_id' => $agent->id, 'exception' => $exception]);
            }
        }

        $this->info("Completed. Tasks created: {$totalCreated}");
        return self::SUCCESS;
    }

    protected function getAgents(?string $agentId)
    {
        return TitanNovaAgent::query()->active()
            ->when($agentId, fn ($query) => $query->where('id', $agentId))
            ->get();
    }

    protected function processAgent(TitanNovaAgent $agent, bool $force = false): int
    {
        if (! $agent->canCreateTasks()) {
            Log::notice('Titan Nova skipped incomplete agent', ['agent_id' => $agent->id]);
            return 0;
        }

        $schedule = $this->buildScheduleContext($agent);
        $targetCount = $this->calculateTaskCount($agent);
        $horizonEnd = now()->addDays($agent->planningHorizonDays());
        $existing = $agent->tasks()
            ->whereIn('status', [TitanNovaTask::STATUS_SCHEDULED, TitanNovaTask::STATUS_PENDING_APPROVAL, TitanNovaTask::STATUS_APPROVED])
            ->whereBetween('scheduled_at', [now(), $horizonEnd])
            ->count();
        $required = $force ? $targetCount : max(0, $targetCount - $existing);
        if ($required === 0) {
            return 0;
        }

        $this->updateCreationStatus($agent, 'creating', [
            'total_requested' => $required,
            'planned_tasks_count' => $targetCount,
            'created_count' => 0,
            'failed_count' => 0,
            'task_horizon_days' => $agent->planningHorizonDays(),
            'started_at' => now()->toIso8601String(),
        ]);

        $created = 0;
        $failed = 0;
        $cursor = $this->getInitialScheduleCursor($agent);
        for ($index = 0; $index < $required; $index++) {
            $task = $this->taskService->createTask($agent, $index);
            if (! ($task['success'] ?? false)) {
                $failed++;
                $this->updateCreationStatus($agent, 'creating', compact('created', 'failed'));
                continue;
            }

            $cursor = $this->getNextScheduleTimeAfter($cursor, $schedule['plan_entries']);
            TitanNovaTask::query()->create([
                'user_id' => $agent->user_id,
                'agent_id' => $agent->id,
                'title' => $task['task_title'],
                'instructions' => $task['task_instructions'],
                'thumbnail' => $task['image_url'] ?? '',
                'labels' => $task['task_labels'],
                'categories' => $task['task_categories'],
                'status' => TitanNovaTask::STATUS_SCHEDULED,
                'scheduled_at' => $cursor,
                'task_type' => $task['task_type'],
                'task_mode' => $task['task_mode'],
                'priority' => $task['priority'],
                'duration_minutes' => $task['duration_minutes'],
                'channel' => $task['channel'],
                'channel_payload' => $task['channel_payload'],
            ]);
            $created++;
            $this->updateCreationStatus($agent, 'creating', [
                'created_count' => $created,
                'failed_count' => $failed,
            ]);
        }

        $this->updateCreationStatus($agent, $failed > 0 && $created === 0 ? 'failed' : 'completed', [
            'created_count' => $created,
            'failed_count' => $failed,
            'completed_at' => now()->toIso8601String(),
        ]);

        return $created;
    }

    protected function updateCreationStatus(TitanNovaAgent $agent, string $status, array $data = []): void
    {
        $payload = array_merge($agent->task_creation_status ?? [], ['status' => $status], $data);
        $agent->update(['task_creation_status' => $payload]);
        TitanNovaTaskCreationCache::mark($agent, $status, $payload);
    }

    protected function calculateTaskCount(TitanNovaAgent $agent): int
    {
        $selectedDays = array_map(fn ($day) => $this->dayNameToNumber((string) $day), $this->resolveScheduleDays($agent->schedule_days ?? []));
        $matchingDays = 0;
        for ($offset = 0; $offset < $agent->planningHorizonDays(); $offset++) {
            if (in_array(now()->addDays($offset)->dayOfWeekIso, $selectedDays, true)) {
                $matchingDays++;
            }
        }
        return max(1, $matchingDays) * max(1, (int) $agent->daily_task_count);
    }

    protected function buildScheduleContext(TitanNovaAgent $agent): array
    {
        $dailyCount = max(1, (int) $agent->daily_task_count);
        $days = $this->resolveScheduleDays($agent->schedule_days ?? []);
        $slots = $this->determineTimeSlots($agent, $dailyCount);
        $perDay = $this->buildPerDayPlan($slots, $dailyCount);
        return ['schedule_days' => $days, 'time_slots' => $slots, 'per_day_plan' => $perDay, 'plan_entries' => $this->buildPlanEntries($days, $perDay)];
    }

    protected function getInitialScheduleCursor(TitanNovaAgent $agent): ?Carbon
    {
        $last = $agent->tasks()->whereNotNull('scheduled_at')->latest('scheduled_at')->value('scheduled_at');
        return $last ? Carbon::parse($last) : null;
    }

    protected function getNextScheduleTimeAfter(?Carbon $after, array $planEntries): Carbon
    {
        $reference = $after?->copy() ?? now()->subMinute();
        $weekStart = $reference->copy()->startOfWeek(Carbon::MONDAY);
        $next = null;
        foreach ($planEntries as $entry) {
            $candidate = $weekStart->copy()->addDays($entry['day_number'] - 1);
            [$hour, $minute] = explode(':', $entry['time']);
            $candidate->setTime((int) $hour, (int) $minute);
            if ($candidate <= $reference) {
                $candidate->addWeek();
            }
            if ($next === null || $candidate->lt($next)) {
                $next = $candidate;
            }
        }
        return $next ?? $reference->copy()->addDay();
    }

    protected function resolveScheduleDays($rawDays): array
    {
        $map = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
        $normalised = [];
        foreach ((array) $rawDays as $day) {
            if (is_numeric($day) && isset($map[(int) $day])) {
                $normalised[] = $map[(int) $day];
                continue;
            }
            foreach ($map as $name) {
                if (is_string($day) && strcasecmp($name, trim($day)) === 0) {
                    $normalised[] = $name;
                    break;
                }
            }
        }
        return array_values(array_unique($normalised ?: ['Monday']));
    }

    protected function determineTimeSlots(TitanNovaAgent $agent, int $dailyCount): array
    {
        $slots = [];
        foreach ($agent->schedule_times ?? [] as $slot) {
            $slots[] = [
                'key' => $slot['key'] ?? 'slot_' . Str::random(5),
                'label' => $slot['label'] ?? 'Task slot',
                'start' => $this->normalizeTimeValue($slot['start'] ?? null, '09:00'),
                'end' => $this->normalizeTimeValue($slot['end'] ?? null, '11:00'),
            ];
        }
        $slots = $slots ?: $this->defaultTimeSlots();
        return array_slice($slots, 0, max(1, min($this->maxSlotsForDailyCount($dailyCount), count($slots))));
    }

    protected function defaultTimeSlots(): array
    {
        return [
            ['key' => 'morning', 'label' => 'Morning', 'start' => '08:00', 'end' => '12:00'],
            ['key' => 'noon', 'label' => 'Noon', 'start' => '12:00', 'end' => '16:00'],
            ['key' => 'evening', 'label' => 'Evening', 'start' => '17:00', 'end' => '22:00'],
        ];
    }

    protected function maxSlotsForDailyCount(int $count): int { return min(3, max(1, $count)); }

    protected function buildPerDayPlan(array $slots, int $dailyCount): array
    {
        $distribution = array_fill(0, count($slots), intdiv($dailyCount, count($slots)));
        for ($i = 0; $i < $dailyCount % count($slots); $i++) { $distribution[$i]++; }
        $plan = [];
        foreach ($slots as $slotIndex => $slot) {
            for ($taskIndex = 0; $taskIndex < $distribution[$slotIndex]; $taskIndex++) {
                $plan[] = ['slot' => $slot['key'], 'time' => $this->calculateTimeForSlot($slot, $taskIndex, $distribution[$slotIndex])];
            }
        }
        return $plan;
    }

    protected function calculateTimeForSlot(array $slot, int $index, int $total): string
    {
        $start = Carbon::createFromFormat('H:i', $slot['start']);
        $end = Carbon::createFromFormat('H:i', $slot['end']);
        $minutes = max(0, $start->diffInMinutes($end));
        return $start->copy()->addMinutes((int) floor(($minutes / max(1, $total)) * $index))->format('H:i');
    }

    protected function buildPlanEntries(array $days, array $perDay): array
    {
        $entries = [];
        foreach ($days as $day) {
            foreach ($perDay as $task) {
                $entries[] = ['day_number' => $this->dayNameToNumber($day), 'time' => $task['time']];
            }
        }
        return $entries;
    }

    protected function dayNameToNumber(string $day): int
    {
        return ['monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6,'sunday'=>7][strtolower($day)] ?? (is_numeric($day) ? max(1, min(7, (int) $day)) : 1);
    }

    protected function normalizeTimeValue(?string $time, string $fallback = '09:00'): string
    {
        try { return Carbon::parse($time ?: $fallback)->format('H:i'); }
        catch (Exception) { return $fallback; }
    }
}
