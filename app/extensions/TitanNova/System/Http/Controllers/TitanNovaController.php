<?php

namespace App\Extensions\TitanNova\System\Http\Controllers;

use App\Extensions\TitanNova\System\Models\TitanNovaAgent;
use App\Extensions\TitanNova\System\Models\TitanNovaTask;
use App\Extensions\TitanNova\System\Services\TaskCreationService;
use App\Extensions\TitanNova\System\Support\TitanNovaTaskCreationCache;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Integration\Integration;
use App\Models\Integration\UserIntegration;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TitanNovaController extends Controller
{
    public function __invoke(): View
    {
        return $this->index();
    }

    /**
     * Dashboard page
     */
    public function index(): View
    {
        $userId = Auth::id();

        // Get all tasks with status counts in one query
        $tasksQuery = TitanNovaTask::query()
            ->where('user_id', $userId);

        // Get counts efficiently
        $pending_tasks_count = (clone $tasksQuery)
            ->where('status', TitanNovaTask::STATUS_DRAFT)
            ->count();

        $scheduled_tasks_count = (clone $tasksQuery)
            ->where('status', TitanNovaTask::STATUS_SCHEDULED)
            ->count();

        $total_tasks_count = $tasksQuery->count();

        // Get paginated tasks
        $tasks = TitanNovaTask::query()
            ->where('user_id', $userId)
            ->orderBy('updated_at', 'desc')
            ->paginate(10);

        $agentsQuery = TitanNovaAgent::query()
            ->where('user_id', $userId);

        $defaultAgent = (clone $agentsQuery)->orderByDesc('created_at')->first();

        $agentIds = (clone $agentsQuery)
            ->pluck('id');

        $new_tasks = (clone $tasksQuery)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $new_impressions = 0;

        $creationStatus = $defaultAgent ? TitanNovaTaskCreationCache::currentStatus($defaultAgent) : ['status' => 'idle'];

        return view('titan-nova::dashboard.index', [
            'pending_tasks_count'   => $pending_tasks_count,
            'scheduled_tasks_count' => $scheduled_tasks_count,
            'total_tasks_count'     => $total_tasks_count,
            'tasks'                 => $tasks,
            'channels'             => '',
            'new_tasks'             => $new_tasks,
            'new_impressions'       => '',
            'creation_status'     => $creationStatus,
            'defaultAgent'          => $defaultAgent,
        ]);
    }

    public function taskItems(Request $request): View
    {
        $userId = Auth::id();

        // Parse filters - convert comma-separated strings to arrays
        $filters = collect($request->except(['page', 'task_style', 'per_page', 'id', 'start_date', 'end_date', 'date_column', 'sort_by', 'sort_direction']))
            ->map(fn ($value) => is_string($value) && str_contains($value, ',')
                ? explode(',', $value)
                : $value
            )
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->toArray();

        $taskStyle = $request->get('task_style', 'carousel');
        $perPage = $request->integer('per_page', 10);

        // Date range filtering
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $dateColumn = $request->input('date_column', 'created_at');

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        // Validate sort column
        $allowedSortColumns = ['created_at', 'scheduled_at', 'status', 'title'];
        if (! in_array($sortBy, $allowedSortColumns, true)) {
            $sortBy = 'created_at';
        }

        // Validate sort direction
        $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        $baseQuery = fn () => TitanNovaTask::query()
            ->whereHas('agent', fn ($q) => $q->where('user_id', $userId))
            ->when($filters, function ($q) use ($filters) {
                foreach ($filters as $key => $value) {
                    if (is_array($value)) {
                        $q->whereIn($key, $value);
                    } else {
                        $q->where($key, $value);
                    }
                }
            })
            ->when($startDate, fn ($q) => $q->where($dateColumn, '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where($dateColumn, '<=', $endDate));

        $query = $baseQuery()
            ->with(['agent', 'agent.user']);

        // For content sorting, use LEFT() to sort by first 100 characters for efficiency
        if ($sortBy === 'title') {
            $query->orderByRaw('LEFT(title, 100) ' . $sortDirection);
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        $tasks = $query->paginate($perPage)->appends($request->except('page'));

        $view = 'titan-nova::components.tasks.carousel.task-items';

        if (! empty($taskStyle)) {
            $view = 'titan-nova::components.tasks.' . $taskStyle . '.task-items';
        }

        return view($view, [
            'tasks' => $tasks,
        ]);
    }

    /**
     * Calendar page
     */
    public function calendar(): View
    {
        $userId = Auth::id();

        $agents = TitanNovaAgent::query()
            ->where('user_id', Auth::id())
            ->with('tasks')
            ->latest()
            ->get();

        return view('titan-nova::calendar.index', [
            'agents' => $agents,
        ]);
    }

    /**
     * Tasks page
     */
    public function tasks(): View
    {
        $userId = Auth::id();

        $tasks = TitanNovaTask::query()
            ->orderBy('scheduled_at', 'desc')
            ->paginate(999);

        $agents = TitanNovaAgent::query()
            ->where('user_id', $userId)
            ->get();

        return view('titan-nova::tasks.index', [
            'tasks'         => $tasks,
            'channelEnums' => '',
            'channels'     => '',
            'agents'        => $agents,
        ]);
    }

    /**
     * Get pending tasks count (API)
     */
    public function getPendingTaskCount(): \Illuminate\Http\JsonResponse
    {
        $userId = Auth::id();

        $count = TitanNovaTask::query()
            ->where('status', 0)
            ->where('user_id', $userId)
            ->count();

        return response()->json([
            'success' => true,
            'count'   => $count,
        ]);
    }

    /**
     * Analytics page
     */
    public function analytics(): View
    {
        $userId = Auth::id();

        $baseQuery = TitanNovaTask::query()
            ->where('user_id', $userId);

        $stats = [
            'total_tasks' => (clone $baseQuery)->count(),

            'draft_tasks' => (clone $baseQuery)
                ->where('status', TitanNovaTask::STATUS_DRAFT)
                ->count(),

            'scheduled_tasks' => (clone $baseQuery)
                ->where('status', TitanNovaTask::STATUS_SCHEDULED)
                ->count(),

            'completed_tasks' => (clone $baseQuery)
                ->where('status', TitanNovaTask::STATUS_COMPLETED)
                ->count(),

            'created_today' => (clone $baseQuery)
                ->whereDate('created_at', today())
                ->count(),
        ];

        $agents = TitanNovaAgent::query()->get();

        $monthRange = $this->buildMonthRange(12);
        $completedChartData = $this->buildCompletedTasksChartData($userId, $agents, $monthRange);
        $newsFeed = $this->buildAnalyticsNews($userId, $stats);

        return view('titan-nova::analytics.index', [
            'stats'              => $stats,
            'agents'             => $agents,
            'news'               => $newsFeed,
            'completedChartData' => $completedChartData,
            'completedMonths'    => $monthRange,
        ]);
    }

    private function buildAnalyticsNews(int $userId, array $stats): array
    {
        $items = [];

        if (($stats['created_today'] ?? 0) > 0) {
            $items[] = __(':count tasks were created today.', ['count' => $stats['scheduled_tasks']]);
        }

        if (($stats['total_tasks'] ?? 0) > 0) {
            $items[] = __('Total of :count tasks are created.', ['count' => $stats['total_tasks']]);
        }

        if (($stats['completed_tasks'] ?? 0) > 0) {
            $items[] = __('Total of :count tasks were completed.', ['count' => $stats['completed_tasks']]);
        }

        if (($stats['scheduled_tasks'] ?? 0) > 0) {
            $items[] = __('Total of :count tasks were scheduled.', ['count' => $stats['scheduled_tasks']]);
        }

        $recentTask = TitanNovaTask::query()
            ->where('user_id', $userId)
            ->latest()
            ->first();

        if ($recentTask) {
            $items[] = __('The last task was created :date.', ['date' => optional($recentTask->created_at)->diffForHumans()]);
        }

        return $items;
    }

    private function buildMonthRange(int $months = 12): array
    {
        $months = max(1, $months);
        $range = [];

        $cursor = now()->copy()->startOfMonth()->subMonths($months - 1);

        for ($i = 0; $i < $months; $i++) {
            $range[] = $cursor->copy();
            $cursor->addMonth();
        }

        return $range;
    }

    private function buildCompletedTasksChartData(int $userId, $agents, array $months): array
    {
        [$months, $monthKeys, $startDate, $endDate] = $this->prepareMonthMetadata($months);

        $records = TitanNovaTask::query()
            ->where('user_id', $userId)
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->get();

        $recordsByAgent = $records->groupBy('agent_id');

        $todayTotals = TitanNovaTask::query()
            ->where('user_id', $userId)
            ->whereNotNull('agent_id')
            ->whereDate('scheduled_at', now()->toDateString())
            ->selectRaw('agent_id, COUNT(*) as total')
            ->groupBy('agent_id')
            ->pluck('total', 'agent_id');

        return $this->buildChartSeriesFromRecords(
            $agents,
            $monthKeys,
            $recordsByAgent,
            $todayTotals,
            'today_tasks'
        );
    }

    private function prepareMonthMetadata(array $months): array
    {
        if (empty($months)) {
            $months = $this->buildMonthRange(12);
        }

        $normalizedMonths = collect($months)
            ->map(fn ($month) => $month instanceof Carbon ? $month->copy() : Carbon::parse($month))
            ->values();

        $monthKeys = $normalizedMonths->map(fn (Carbon $month) => $month->format('Y-m'))->values();

        return [
            $normalizedMonths->all(),
            $monthKeys->all(),
            $normalizedMonths->first()->copy(),
            $normalizedMonths->last()->copy()->endOfMonth(),
        ];
    }

    private function buildChartSeriesFromRecords($agents, array $monthKeys, $recordsByAgent, $currentTotals, string $statKey, bool $asFloat = false): array
    {
        $allSeries = array_fill(0, count($monthKeys), 0);
        $chartData = [];

        foreach ($agents as $agent) {
            $agentId = $agent->id;
            $agentName = $agent->name ?: ('agent_' . $agentId);

            $agentData = $recordsByAgent
                ->get($agentId, collect())
                ->groupBy(function ($row) {
                    return Carbon::parse($row->scheduled_at)->format('Y-m');
                })
                ->map(function ($rows) {
                    return [
                        'total' => $rows->count(),
                    ];
                });

            $seriesData = [];

            foreach ($monthKeys as $index => $key) {
                $value = (float) data_get($agentData->get($key), 'total', 0);
                $seriesData[] = $value;
                $allSeries[$index] += $value;
            }

            $chartData[] = [
                'label'        => Str::headline($agentName),
                'id'           => $agentId,
                'chart_series' => [
                    'name'   => $agentName,
                    'data'   => $seriesData,
                    'hidden' => true,
                ],
                $statKey      => $asFloat
                    ? round((float) ($currentTotals[$agentId] ?? 0), 2)
                    : (int) ($currentTotals[$agentId] ?? 0),
            ];
        }

        $chartData = array_values($chartData);
        array_unshift($chartData, [
            'label'        => __('All'),
            'id'           => '*',
            'chart_series' => [
                'name' => 'all',
                'data' => $allSeries,
            ],
            $statKey       => $asFloat
                ? round((float) collect($currentTotals)->sum(), 2)
                : (int) collect($currentTotals)->sum(),
        ]);

        return $chartData;
    }

    private function decodeJsonField($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return (array) $decoded;
            }
        }

        return [];
    }

    private function sanitizeScheduleTimes(array $times): array
    {
        return collect($times)
            ->map(function ($slot) {
                if (! is_array($slot)) {
                    return null;
                }

                $start = $slot['start'] ?? null;
                $end = $slot['end'] ?? null;

                if (! $start || ! $end) {
                    return null;
                }

                return array_filter([
                    'key'   => $slot['key'] ?? null,
                    'label' => $slot['label'] ?? null,
                    'start' => $start,
                    'end'   => $end,
                ], fn ($value) => $value !== null);
            })
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeScheduleDays(array $days): array
    {
        $map = $this->getWeekDayMap();

        return collect($days)
            ->map(function ($day) use ($map) {
                if (is_numeric($day)) {
                    $intDay = (int) $day;

                    if ($intDay >= 1 && $intDay <= 7) {
                        return $map[$intDay] ?? null;
                    }

                    if ($intDay >= 0 && $intDay <= 6) {
                        $converted = (($intDay + 6) % 7) + 1; // Accept JS-style 0=Sunday

                        return $map[$converted] ?? null;
                    }

                    return null;
                }

                $normalized = strtolower(trim((string) $day));
                foreach ($map as $name) {
                    if ($normalized === strtolower($name)) {
                        return $name;
                    }
                }

                try {
                    return Carbon::parse($day)->format('l');
                } catch (Exception) {
                    return null;
                }
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function sanitizeString($value, int $maxLength = 500): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return Str::substr($trimmed, 0, $maxLength);
    }

    private function getWeekDayMap(): array
    {
        return [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];
    }

    /**
     * Show create wizard - Step 1: Platform Selection
     */
    public function create(): View
    {
        return view('titan-nova::create.index');
    }

    /**
     * Store the agent (Final wizard step)
     */
    public function store(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'success' => false,
                'message' => __('This action is not allowed in the demo mode.'),
            ], 403);
        }

        if ($response = $this->ensureAgentCreationAllowed()) {
            return $response;
        }

        $validated = $request->validate([
            'name'                      => 'required|string|max:255',
            'task_type_options'             => 'nullable|array',
            'task_type_options.*'           => 'string',
            'selected_task_types'           => 'nullable|array|min:1',
            'selected_task_types.*'         => 'string',
            'task_modes'                => 'required|array|min:1',
            'task_modes.*'              => 'string',
            'has_image'                 => 'nullable|in:0,1',
            'has_emoji'                 => 'nullable|in:0,1',
            'has_web_search'            => 'nullable|in:0,1',
            'has_keyword_search'        => 'nullable|in:0,1',
            'language'                  => 'required|string',
            'default_task_duration_minutes'            => 'required|integer|min:5|max:1440',
            'priority'                      => 'required|in:low,normal,high,urgent',
            'frequency'                 => 'required|in:weekly,monthly',
            'daily_task_count'          => 'required|integer|min:1|max:10',
            'schedule_days'             => 'nullable|array',
            'schedule_days.*'           => 'string',
            'schedule_times'            => 'nullable',
        ]);

        // Decode JSON strings
        $scheduleDaysInput = $validated['schedule_days'] ?? [];
        $scheduleTimes = $this->sanitizeScheduleTimes($this->decodeJsonField($validated['schedule_times'] ?? []));

        // Convert string booleans to actual booleans
        $hasImage = ($validated['has_image'] ?? '0') == '1';
        $hasEmoji = ($validated['has_emoji'] ?? '0') == '1';
        $hasWebSearch = ($validated['has_web_search'] ?? '0') == '1';
        $hasKeywordSearch = ($validated['has_keyword_search'] ?? '0') == '1';
        $normalizedScheduleDays = $this->normalizeScheduleDays($scheduleDaysInput);

        if (empty($normalizedScheduleDays)) {
            throw ValidationException::withMessages([
                'schedule_days' => __('Please select at least one day or enable AI scheduling.'),
            ]);
        }

        if (empty($scheduleTimes)) {
            throw ValidationException::withMessages([
                'schedule_times' => __('Please choose at least one time slot.'),
            ]);
        }

        $agent = TitanNovaAgent::create([
            'user_id'                => Auth::id(),
            'name'                   => $validated['name'],
            'task_type_options'          => $validated['task_type_options'],
            'selected_task_types'        => $validated['selected_task_types'],
            'task_modes'             => $validated['task_modes'],
            'has_image'              => $hasImage,
            'has_emoji'              => $hasEmoji,
            'has_web_search'         => $hasWebSearch,
            'has_keyword_search'     => $hasKeywordSearch,
            'language'               => $validated['language'],
            'default_task_duration_minutes'         => (string) $validated['default_task_duration_minutes'],
            'default_task_duration_minutes' => (int) $validated['default_task_duration_minutes'],
            'priority'                   => $validated['priority'],
            'frequency'              => $validated['frequency'],
            'task_horizon_days'      => $validated['frequency'] === 'monthly' ? 30 : 7,
            'daily_task_count'       => $validated['daily_task_count'],
            'schedule_days'          => $normalizedScheduleDays,
            'schedule_times'         => $scheduleTimes,
            'is_active'              => true,
            'task_creation_status' => [
                'status' => 'idle',
            ],
        ]);

        $this->queueTaskCreation($agent);

        return response()->json([
            'success'  => true,
            'agent_id' => $agent->id,
            'message'  => __('Agent created successfully! Tasks are being created and scheduled in the background.'),
        ]);
    }

    public function agents(): View
    {
        $agents = TitanNovaAgent::query()
            ->where('user_id', Auth::id())
            ->get();

        $determineAgentOfMonth = $this->determineAgentOfMonth($agents);

        return view('titan-nova::agents.index', [
            'agents'                => $agents,
            'determineAgentOfMonth' => $determineAgentOfMonth,
        ]);
    }

    /**
     * Show edit form
     */
    public function edit(TitanNovaAgent $agent): View
    {
        $this->authorize('update', $agent);

        return view('titan-nova::agent.edit', [
            'agent'     => $agent,
        ]);
    }

    protected function determineAgentOfMonth(Collection $agents): ?TitanNovaAgent
    {
        if ($agents->isEmpty()) {
            return null;
        }

        $activeAgents = $agents->filter->is_active;
        $pool = $activeAgents->isNotEmpty() ? $activeAgents : $agents;

        return $pool
            ->sortByDesc(function (TitanNovaAgent $agent) {
                $engagement = $agent->average_engagement ?? 0;
                $impressions = $agent->average_impressions ?? 0;
                $created = optional($agent->created_at)->timestamp ?? 0;

                return ($engagement * 1000) + $impressions + ($created / 100000);
            })
            ->first();
    }

    /**
     * Update agent
     */
    public function update(Request $request, TitanNovaAgent $agent): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return redirect()
                ->route('dashboard.user.titan-nova.agent.edit', $agent->id)
                ->with([
                    'type'    => 'error',
                    'message' => __('This action is not allowed in the demo mode.'),
                ]);
        }

        $this->authorize('update', $agent);

        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'selected_task_types'    => 'required|array|min:1',
            'task_modes'         => 'required|array|min:1',
            'is_active'          => 'boolean',
            'daily_task_count'   => 'required|integer|min:1|max:10',
        ]);

        $validated['is_active'] = (bool) $request->has('is_active');
        $validated['has_image'] = (bool) $request->has('has_image');
        $validated['has_emoji'] = (bool) $request->has('has_emoji');
        $validated['has_web_search'] = (bool) $request->has('has_web_search');
        $validated['has_keyword_search'] = (bool) $request->has('has_keyword_search');

        $agent->update($validated);

        return redirect()
            ->route('dashboard.user.titan-nova.agent.edit', $agent->id)
            ->with([
                'status'  => 'success',
                'message' => __('Agent updated successfully!'),
                'type'    => 'success',
                'success' => __('Agent updated successfully!'),
            ]);
    }

    /**
     * Delete agent
     */
    public function destroy(TitanNovaAgent $agent): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return redirect()
                ->route('dashboard.user.titan-nova.agent.agents')
                ->with([
                    'type'    => 'error',
                    'message' => __('This action is not allowed in the demo mode.'),
                ]);
        }

        $this->authorize('delete', $agent);

        $agent->delete();

        return redirect()
            ->route('dashboard.user.titan-nova.agent.agents');
    }

    // ==================== WIZARD AJAX ENDPOINTS ====================

    /**
     * AJAX: Generate topics (step 2)
     */
    public function generateTaskTypes(Request $request): JsonResponse
    {
        $topic = trim((string) $request->input('topic'));

        $request->validate([
            'topic' => 'required|string',
        ]);

        $taskService = new TaskCreationService;
        $topics = $taskService->generateTaskTypes($topic);

        return response()->json([
            'success' => true,
            'topics'  => $topics,
        ]);
    }

    // ==================== TASK MANAGEMENT ====================

    /**
     * Get tasks (API)
     */
    public function getTasks(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $query = TitanNovaTask::query()
            ->where('user_id', $userId)
            ->whereDate('scheduled_at', '>=', $this->parseDateOrNull($request->input('start_date')))
            ->whereDate('scheduled_at', '<=', $this->parseDateOrNull($request->input('end_date')));

        $perPage = (int) $request->integer('per_page', 10);
        $tasks = $query->paginate($perPage)->appends($request->except('page'));

        return response()->json([
            'success' => true,
            'tasks'   => $tasks,
            'tasks'   => $tasks,
        ]);
    }

    /**
     * Edit a task
     */
    public function editTask(TitanNovaTask $task): View
    {
        return view('titan-nova::components.edit-task', [
            'task' => $task,
        ]);
    }

    /**
     * Run a task through its configured execution mode.
     */
    public function runTaskAjax(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'success' => false,
                'message' => trans('This feature is disabled in demo mode.'),
            ], 403);
        }

        $validated = $request->validate([
            'id' => 'required',
        ]);

        $this->runTask($validated['id']);

        $task = TitanNovaTask::query()->find($validated['id']);
        $message = $task?->status === TitanNovaTask::STATUS_SCHEDULED
            ? trans('Task remains scheduled')
            : trans('Task dispatch state updated');

        return response()->json([
            'success' => true,
            'message' => $message,
            'status'  => $task?->status,
        ]);
    }

    /**
     * Reject/delete a task
     */
    public function rejectTask(TitanNovaTask $task): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'success' => false,
                'message' => __('This action is not allowed in the demo mode.'),
            ], 403);
        }

        $this->authorize('update', $task->agent);

        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task rejected and deleted.',
        ]);
    }

    /**
     * Duplicate a task
     */
    public function duplicateTask(TitanNovaTask $task): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'success' => false,
                'message' => __('This action is not allowed in the demo mode.'),
            ], 403);
        }

        $this->authorize('update', $task->agent);

        $duplicate = $task->replicate([
            'status',
            'scheduled_at',
            'completed_at',
        ]);

        $duplicate->status = TitanNovaTask::STATUS_DRAFT;
        $duplicate->scheduled_at = null;
        $duplicate->completed_at = null;
        $duplicate->save();

        return response()->json([
            'success' => true,
            'message' => __('Task duplicated successfully.'),
            'task'    => $duplicate->fresh(['agent']),
        ]);
    }

    /**
     * Duplicate a task
     */
    public function updateTask(Request $request)
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'success' => false,
                'message' => __('This action is not allowed in the demo mode.'),
            ], 403);
        }

        if ($request->id !== 'undefined') {
            $task = TitanNovaTask::where('id', $request->id)->firstOrFail();
        } else {
            $task = new TitanNovaTask;
        }

        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'content'      => 'required|string',
            'tags'         => 'string',
            'categories'   => 'string',
            'status'       => 'required|string',
            'scheduled_at'     => 'required|string',
            'task_type'        => 'nullable|string|max:255',
            'task_mode'        => 'nullable|in:manual,ai_assisted,automated,approval_required,external_workflow',
            'priority'         => 'nullable|in:low,normal,high,urgent',
            'duration_minutes' => 'nullable|integer|min:5|max:1440',
            'channel' => 'nullable|in:manual,titan_action',
        ]);

        $status = $validated['status'];
        if ($status === TitanNovaTask::STATUS_COMPLETED && ! empty($validated['scheduled_at'])) {
            $scheduledAt = Carbon::parse($validated['scheduled_at']);
            if ($scheduledAt->isFuture()) {
                $status = TitanNovaTask::STATUS_SCHEDULED;
            }
        }

        // Fill only allowed fields
        $task->fill([
            'title'        => $validated['title'],
            'content'      => $validated['content'],
            'status'       => $status,
            'scheduled_at'     => $validated['scheduled_at'],
            'task_type'        => $validated['task_type'] ?? $task->task_type,
            'task_mode'        => $validated['task_mode'] ?? $task->task_mode,
            'priority'         => $validated['priority'] ?? $task->priority,
            'duration_minutes' => $validated['duration_minutes'] ?? $task->duration_minutes,
            'channel' => $validated['channel'] ?? $task->channel,
        ]);

        // If tags & categories are JSON columns
        if (array_key_exists('tags', $validated)) {
            $task->tags = explode(',', $validated['tags']);
        }

        if (array_key_exists('categories', $validated)) {
            $task->categories = explode(',', $validated['categories']);
        }

        if (array_key_exists('thumbnail', $validated)) {
            $task->thumbnail = $validated['thumbnail'];
        }

        if ($request->hasFile('thumbnail')) {
            $path = 'uploads/images/titan-nova/';
            $image = $request->file('thumbnail');
            $image_name = Str::random(4) . '-' . Str::slug($request->slug) . '.' . $image->guessExtension();

            // Resim uzantı kontrolü
            $imageTypes = ['jpg', 'jpeg', 'png', 'svg', 'webp'];
            if (! in_array(Str::lower($image->guessExtension()), $imageTypes)) {
                $data = [
                    'errors' => ['The file extension must be jpg, jpeg, png, webp or svg.'],
                ];

                return response()->json($data, 419);
            }

            $image->move($path, $image_name);

            $thumbnail = $path . $image_name;

            $task->thumbnail = $thumbnail ?? $task->thumbnail;
        }

        $task->save();

        return response()->json([
            'success' => true,
            'task'    => $task,
        ]);
    }

    // ==================== HELPER METHODS ====================

    public function runTask($task_id, $user_id = '')
    {
        $userId = ! empty($user_id) ? $user_id : Auth::id();
        $task = TitanNovaTask::query()
            ->where('id', $task_id)
            ->where('user_id', $userId)
            ->firstOrFail();

        if ($task->scheduled_at && Carbon::parse($task->scheduled_at)->isFuture()) {
            $task->update(['status' => TitanNovaTask::STATUS_SCHEDULED]);
            return $task;
        }

        if ($task->task_mode === TitanNovaTask::MODE_APPROVAL_REQUIRED && ! $task->approved_at) {
            $task->update(['status' => TitanNovaTask::STATUS_PENDING_APPROVAL]);
            return $task;
        }

        $task->markAsStarted();

        if ($task->channel === 'manual') {
            $task->update([
                'status' => TitanNovaTask::STATUS_APPROVED,
                'channel_receipt' => [
                    'status' => 'awaiting_manual_completion',
                    'dispatched_at' => now()->toIso8601String(),
                ],
            ]);
            return $task->fresh();
        }

        if ($task->channel !== 'titan_action') {
            return $task->markAsFailed('Unsupported task execution driver.');
        }

        if (! app()->bound('titan.capability.dispatcher')) {
            $task->update([
                'status' => TitanNovaTask::STATUS_APPROVED,
                'channel_receipt' => [
                    'status' => 'blocked_missing_dispatcher',
                    'required_binding' => 'titan.capability.dispatcher',
                    'blocked_at' => now()->toIso8601String(),
                ],
            ]);
            return $task->fresh();
        }

        try {
            $dispatcher = app('titan.capability.dispatcher');
            $result = $dispatcher->dispatch(
                (string) data_get($task->channel_payload, 'capability'),
                (array) data_get($task->channel_payload, 'payload', []),
                [
                    'user_id' => $userId,
                    'agent_id' => $task->agent_id,
                    'task_id' => $task->id,
                ],
            );

            return $task->markAsCompleted([
                'status' => 'completed',
                'result' => $result,
                'completed_at' => now()->toIso8601String(),
            ]);
        } catch (Exception $exception) {
            Log::error('Titan Nova task execution failed', ['task_id' => $task->id, 'exception' => $exception]);
            return $task->markAsFailed($exception->getMessage());
        }
    }


    protected function queueTaskCreation(TitanNovaAgent $agent): void
    {
        TitanNovaTaskCreationCache::forgetForUser($agent->user_id);
        $stats = TitanNovaTaskCreationCache::computeTaskStats($agent);
        $plannedTaskCount = $this->estimatePlannedCreationCount($agent);

        Log::info("Queuing task creation for Agent ID {$agent->id} with planned count {$plannedTaskCount}.");

        $agent->update([
            'task_creation_status' => array_merge($agent->task_creation_status ?? [], [
                'status' => 'queued',
                'queued_at' => now()->toDateTimeString(),
                'planned_tasks_count' => $plannedTaskCount,
            ]),
        ]);

        TitanNovaTaskCreationCache::mark($agent, 'queued', [
            'queued_at' => now()->toIso8601String(),
            'created_count' => 0,
            'failed_count' => 0,
            'total_requested' => $plannedTaskCount,
            'planned_tasks_count' => $plannedTaskCount,
        ] + $stats);
    }


    public function getTaskCreationStatus(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $status = TitanNovaTaskCreationCache::getForUser($userId);

        $agent = null;
        $agentIdFromStatus = (int) data_get($status, 'agent_id');

        if ($agentIdFromStatus > 0) {
            $agent = TitanNovaAgent::query()
                ->where('user_id', $userId)
                ->where('id', $agentIdFromStatus)
                ->first();
        }

        if (! $agent) {
            $agent = TitanNovaAgent::query()
                ->where('user_id', $userId)
                ->orderByDesc('created_at')
                ->first();
        }

        if (! $agent) {
            return response()->json([
                'success' => false,
                'message' => __('Agent not found.'),
            ], 404);
        }

        if (! $status) {
            $status = [
                'agent_id'        => $agent->id,
                'status'          => data_get($agent->task_creation_status, 'status', 'idle'),
                'updated_at'      => data_get($agent->task_creation_status, 'updated_at'),
                'created_count' => (int) data_get($agent->task_creation_status, 'created_count', 0),
            ];
        }

        if (! array_key_exists('created_count', $status)) {
            $status['created_count'] = (int) data_get($agent->task_creation_status, 'created_count', 0);
        }

        $stats = TitanNovaTaskCreationCache::computeTaskStats($agent);
        $pendingCount = $stats['pending_tasks_count'];
        $scheduledCount = $stats['scheduled_tasks_count'];
        $totalCount = $stats['total_tasks_count'];
        $status = array_merge($status, $stats);
        $plannedCount = (int) data_get($status, 'total_requested', data_get($status, 'planned_tasks_count', 0));

        return response()->json([
            'success'               => true,
            'status'                => $status,
            'total_tasks_count'     => $totalCount,
            'total_tasks_count'     => $totalCount,
            'pending_tasks_count'   => $pendingCount,
            'pending_tasks_count'   => $pendingCount,
            'scheduled_tasks_count' => $scheduledCount,
            'scheduled_tasks_count' => $scheduledCount,
            'created_tasks_count'   => (int) data_get($status, 'created_count', data_get($status, 'created_count', 0)),
            'created_tasks_count' => (int) data_get($status, 'created_count', data_get($status, 'created_count', 0)),
            'planned_tasks_count'   => $plannedCount,
            'planned_tasks_count'   => $plannedCount,
            'ready_text_template'   => __(':generated of :total tasks are being created.'),
        ]);
    }

    private function parseDateOrNull(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Webhook endpoint for Fal.ai image creation results
     */
    public function channelWebhook(Request $request): JsonResponse
    {
        try {
            Log::info('Channel webhook received', $request->all());

            $requestId = $request->input('request_id');
            $status = $request->input('status');
            $task = TitanNovaTask::query()->find($request->input('task_id', $request->input('task_id')));

            if (! $requestId || ! $task) {
                return response()->json(['error' => 'Missing request_id or task_id'], 400);
            }

            if ($status === 'COMPLETED') {
                $imageUrl = $request->input('images.0.url');

                if ($imageUrl) {
                    // Download and store the image
                    $imageService = new \App\Extensions\TitanNova\System\Services\ImageGenerationService;
                    $storedPath = $this->downloadAndStoreImage($imageUrl);

                    // Update task with image
                    $task->update([
                        'media_urls'   => [$storedPath],
                        'image_status' => 'completed',
                    ]);

                    Log::info("Image generated successfully for task {$task->id}");
                }
            } elseif ($status === 'FAILED') {
                $task->update([
                    'image_status' => 'failed',
                ]);

                Log::error("Image creation failed for task {$task->id}");
            }

            return response()->json(['success' => true]);
        } catch (Exception $e) {
            Log::error('Channel webhook error: ' . $e->getMessage());

            return response()->json(['error' => 'Internal error'], 500);
        }
    }

    /**
     * Download and store image from URL
     */
    protected function downloadAndStoreImage(string $url): string
    {
        $imageContents = file_get_contents($url);
        $filename = 'titan-nova/' . uniqid('task_', true) . '.png';

        Storage::disk('public')->put($filename, $imageContents);

        return Storage::disk('public')->url($filename);
    }

    private function estimatePlannedCreationCount(TitanNovaAgent $agent): int
    {
        $count = $this->estimateTargetTasksCount($agent);

        return $count;
    }

    private function estimateTargetTasksCount(TitanNovaAgent $agent): int
    {
        $tasksPerDay = max(1, (int) $agent->daily_task_count);
        $selectedDays = array_values(array_unique(array_map('intval', $agent->schedule_days ?? [])));
        $matchingDays = 0;

        for ($offset = 0; $offset < $agent->planningHorizonDays(); $offset++) {
            if (in_array(now()->addDays($offset)->dayOfWeekIso, $selectedDays, true)) {
                $matchingDays++;
            }
        }

        return max(1, $matchingDays) * $tasksPerDay;
    }

    private function ensureAgentCreationAllowed(): ?JsonResponse
    {
        $limit = $this->getTitanNovaAgentLimitValue('agents');

        if ($limit === 0) {
            return $this->titanNovaLimitErrorResponse(__('Your current plan does not allow creating Titan Nova agents.'));
        }

        if ($limit > 0) {
            $agentCount = TitanNovaAgent::query()
                ->where('user_id', Auth::id())
                ->count();

            if ($agentCount >= $limit) {
                return $this->titanNovaLimitErrorResponse(__('You have reached the maximum number of agents included in your plan.'));
            }
        }

        return null;
    }

    private function getTitanNovaAgentLimitValue(string $key): int
    {
        $plan = Auth::user()?->relationPlan;
        $limits = (array) ($plan->titan-nova_limits ?? []);
        $value = $limits[$key] ?? -1;

        return is_numeric($value) ? (int) $value : -1;
    }

    private function titanNovaLimitErrorResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 422);
    }
}
