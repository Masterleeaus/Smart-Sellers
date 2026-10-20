<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Models;

use App\Extensions\AIAgent\System\Enums\TriggerTypeEnum;
use App\Extensions\AIAgent\System\Enums\WorkflowStatusEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AIAgentWorkflow extends Model
{
    protected $table = 'ext_ai_agent_workflows';

    protected $fillable = [
        'user_id',
        'webhook_public_id',
        'tenant_id',
        'webhook_secret_ref',
        'webhook_nonces',
        'name',
        'description',
        'role',
        'avatar',
        'status',
        'trigger_type',
        'trigger_config',
        'actions',
        'steps',
        'last_run_at',
        'next_run_at',
    ];

    protected $appends = ['avatar_url'];

    protected $casts = [
        'status'           => WorkflowStatusEnum::class,
        'trigger_type'     => TriggerTypeEnum::class,
        'trigger_config'   => 'array',
        'actions'          => 'array',
        'steps'            => 'array',
        'webhook_nonces'   => 'array',
        'last_run_at'      => 'datetime',
        'next_run_at'      => 'datetime',
    ];

    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        if (str_starts_with($this->avatar, 'http') || str_starts_with($this->avatar, '/')) {
            return $this->avatar;
        }

        return '/uploads/' . $this->avatar;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(AIAgentChannel::class, 'channel_id');
    }

    public function getChannelIdAttribute(): ?int
    {
        $id = $this->trigger_config['channel_id'] ?? null;

        return $id !== null ? (int) $id : null;
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AIAgentWorkflowRun::class, 'workflow_id');
    }

    public function lastRun(): HasMany
    {
        return $this->hasMany(AIAgentWorkflowRun::class, 'workflow_id')->latest()->limit(1);
    }

    public function isActive(): bool
    {
        return $this->status === WorkflowStatusEnum::Active;
    }

    /**
     * Returns the steps array, falling back to converting legacy actions if steps is null.
     *
     * @return array<int, array{id: string, type: string, app: string, action: string, config: array, order: int}>
     */
    public function getResolvedStepsAttribute(): array
    {
        if ($this->steps !== null) {
            return $this->steps;
        }

        return $this->legacyActionsAsSteps();
    }

    /**
     * Convert old `actions` array into the new `steps` shape for backward compatibility.
     *
     * @return array<int, array{id: string, type: string, app: string, action: string, config: array, order: int}>
     */
    private function legacyActionsAsSteps(): array
    {
        $appMap = [
            'ai_call'         => 'AI',
            'send_message'    => 'Channels',
            'generate_report' => 'Utilities',
        ];

        return array_values(
            array_map(function (array $action, int $index) use ($appMap): array {
                $type = $action['type'] ?? '';

                return [
                    'id'     => Str::uuid()->toString(),
                    'type'   => $type,
                    'app'    => $appMap[$type] ?? 'Action',
                    'action' => $type,
                    'config' => $action['config'] ?? [],
                    'order'  => $index,
                ];
            }, $this->actions ?? [], array_keys($this->actions ?? [])),
        );
    }
}
