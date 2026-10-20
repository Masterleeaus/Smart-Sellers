<?php

namespace App\Extensions\TitanNova\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\TitanNova\System\Models\TitanNovaAgent;
use App\Extensions\TitanNova\System\Models\TitanNovaTask;
use App\Helpers\Classes\ApiHelper;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Parsedown;

/**
 * Titan Nova task-creation service derived from the original agent scheduling engine.
 */
class TaskCreationService
{
    protected string $model;
    protected ?ImageGenerationService $imageService = null;

    public function __construct()
    {
        $this->model = EntityEnum::GPT_5_MINI->value;
    }

    public function createTask(TitanNovaAgent|Builder|Model $agent, int $taskIndex = 0): array
    {
        try {
            ApiHelper::setOpenAiKey();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . ApiHelper::setOpenAiKey(),
                'Content-Type'  => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model'       => $this->model,
                'messages'    => $this->buildTaskPrompt($agent, $taskIndex),
                'temperature' => $this->getTemperature('medium'),
                'max_tokens'  => 1400,
            ]);

            if ($response->failed()) {
                Log::error('Titan Nova task creation API error: ' . $response->body());
                return ['success' => false, 'error' => 'Failed to create task'];
            }

            $content = (string) $response->json('choices.0.message.content');
            $task = $this->decodeJsonObject($content);
            if ($task === null) {
                return ['success' => false, 'error' => 'Invalid AI task response format'];
            }

            $instructions = (string) ($task['task_instructions'] ?? '');
            if ($instructions !== '') {
                $task['task_instructions'] = (new Parsedown)->text($instructions);
            }

            $task['task_title'] = trim((string) ($task['task_title'] ?? 'Untitled task'));
            $task['task_type'] = trim((string) ($task['task_type'] ?? 'general'));
            $task['task_mode'] = $this->normaliseTaskMode((string) ($task['task_mode'] ?? TitanNovaTask::MODE_MANUAL));
            $task['priority'] = $this->normalisePriority((string) ($task['priority'] ?? $agent->priority ?? 'normal'));
            $task['duration_minutes'] = max(5, min(1440, (int) ($task['duration_minutes'] ?? $agent->defaultTaskDurationMinutes())));
            $task['task_labels'] = array_values(array_filter((array) ($task['task_labels'] ?? []), 'is_string'));
            $task['task_categories'] = array_values(array_filter((array) ($task['task_categories'] ?? []), 'is_string'));
            $task['channel'] = trim((string) ($task['channel'] ?? 'manual'));
            $task['channel_payload'] = (array) ($task['channel_payload'] ?? []);
            $task['success'] = true;

            if ($agent->has_image) {
                $image = $this->getImageService()->generateAttachmentForTask($task['task_title']);
                if (isset($image['image_url'])) {
                    $task['image_url'] = ltrim((string) $image['image_url'], '/');
                }
            }

            return $task;
        } catch (Exception $exception) {
            Log::error('Titan Nova task creation error: ' . $exception->getMessage());
            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    /** @deprecated Compatibility wrapper. */

    protected function getImageService(): ImageGenerationService
    {
        return $this->imageService ??= new ImageGenerationService;
    }

    public function createBulkTasks(TitanNovaAgent $agent, int $count = 5): array
    {
        $tasks = [];
        for ($index = 0; $index < $count; $index++) {
            $task = $this->createTask($agent, $index);
            if ($task['success'] ?? false) {
                $tasks[] = $task;
            }
            if ($index < $count - 1) {
                usleep(500000);
            }
        }
        return $tasks;
    }

    /** @deprecated Compatibility wrapper. */

    protected function buildTaskPrompt(TitanNovaAgent $agent, int $taskIndex = 0): array
    {
        $types = $agent->selected_task_types ?? [];
        $modes = $this->parseTaskTypesPrompt($agent->task_modes ?? []);
        $selectedType = $types ? $types[$taskIndex % count($types)] : 'general operational task';
        $selectedMode = $modes ? $modes[$taskIndex % count($modes)] : TitanNovaTask::MODE_MANUAL;

        $input = [
            'agent_name'              => $agent->name,
            'task_type'               => $selectedType,
            'task_mode'               => $selectedMode,
            'priority'                => $agent->priority ?: 'normal',
            'duration_minutes'        => $agent->defaultTaskDurationMinutes(),
            'language'                => $agent->language,
            'research_before_creation'=> (bool) $agent->has_web_search,
            'use_existing_context'    => (bool) $agent->has_keyword_search,
            'require_attachment'      => (bool) $agent->has_image,
            'send_status_updates'     => (bool) $agent->has_emoji,
        ];

        if ($agent->has_web_search) {
            $input['research_context'] = $this->webSearch($selectedType);
        }

        Log::info('Titan Nova task creation prompt', $input);

        return [
            [
                'role' => 'system',
                'content' => 'You create one precise business task for an autonomous task agent. Return valid JSON only with: task_title, task_instructions, task_type, task_mode, priority, duration_minutes, task_labels, task_categories, channel, channel_payload. channel must be manual or titan_action. Never claim a task has executed. Instructions must include completion evidence and escalation conditions.',
            ],
            ['role' => 'user', 'content' => json_encode($input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)],
        ];
    }

    /** @deprecated Compatibility wrapper. */

    protected function parseTaskTypesPrompt(array $taskTypes): array
    {
        $allowed = [
            TitanNovaTask::MODE_MANUAL,
            TitanNovaTask::MODE_AI_ASSISTED,
            TitanNovaTask::MODE_AUTOMATED,
            TitanNovaTask::MODE_APPROVAL_REQUIRED,
            TitanNovaTask::MODE_EXTERNAL_WORKFLOW,
        ];

        return array_values(array_filter(array_map(
            fn ($type) => in_array($type, $allowed, true) ? $type : null,
            $taskTypes
        )));
    }

    protected function webSearch(string $input): string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . ApiHelper::setOpenAiKey(),
                'Content-Type'  => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/responses', [
                'model' => $this->model,
                'input' => $input,
                'tools' => [['type' => 'web_search']],
                'tool_choice' => 'auto',
            ]);

            $data = $response->json();
            return (string) (collect($data['output'] ?? [])
                ->flatMap(fn ($item) => $item['content'] ?? [])
                ->firstWhere('type', 'output_text')['text'] ?? '');
        } catch (Exception $exception) {
            Log::error('Titan Nova task research error: ' . $exception->getMessage());
            return '';
        }
    }

    protected function decodeJsonObject(string $content): ?array
    {
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        $json = $start !== false && $end !== false && $end > $start
            ? substr($content, $start, $end - $start + 1)
            : $content;
        $decoded = json_decode(trim($json), true);
        if (! is_array($decoded)) {
            Log::warning('Titan Nova could not parse task JSON', ['content' => $content]);
            return null;
        }
        return $decoded;
    }

    protected function normaliseTaskMode(string $mode): string
    {
        $allowed = $this->parseTaskTypesPrompt([$mode]);
        return $allowed[0] ?? TitanNovaTask::MODE_MANUAL;
    }

    protected function normalisePriority(string $priority): string
    {
        return in_array($priority, ['low', 'normal', 'high', 'urgent'], true) ? $priority : 'normal';
    }

    protected function getTemperature(?string $creativity): float
    {
        return match ($creativity) {
            'low' => 0.3,
            'high' => 0.8,
            default => 0.5,
        };
    }

    public function setModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function generateTaskTypes(string $description): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . ApiHelper::setOpenAiKey(),
            'Content-Type'  => 'application/json',
        ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'Generate concise business task types. Return comma-separated text only.'],
                ['role' => 'user', 'content' => "Create 10 distinct task types for this agent purpose: {$description}"],
            ],
            'temperature' => $this->getTemperature('medium'),
            'max_tokens' => 500,
        ]);

        if ($response->failed()) {
            Log::error('Titan Nova task-type creation API error: ' . $response->body());
            return ['success' => false, 'error' => 'Failed to generate task types'];
        }

        return [
            'success' => true,
            'topic_data' => array_values(array_filter(array_map('trim', explode(',', (string) $response->json('choices.0.message.content'))))),
        ];
    }

}
