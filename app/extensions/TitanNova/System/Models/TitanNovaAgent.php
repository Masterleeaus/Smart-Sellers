<?php

namespace App\Extensions\TitanNova\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TitanNovaAgent extends Model
{
    use SoftDeletes;

    protected $table = 'ext_titan_nova_agents';

    protected $fillable = [
        'user_id', 'name', 'task_type_options', 'selected_task_types', 'task_modes',
        'channels', 'has_image', 'has_emoji', 'has_web_search', 'has_keyword_search',
        'language', 'default_task_duration_minutes', 'priority', 'frequency',
        'daily_task_count', 'task_horizon_days', 'schedule_days', 'schedule_times',
        'is_active', 'task_creation_status', 'task_engine_config',
    ];

    protected $casts = [
        'user_id' => 'integer', 'task_type_options' => 'array', 'selected_task_types' => 'array',
        'task_modes' => 'array', 'channels' => 'array', 'has_image' => 'boolean',
        'has_emoji' => 'boolean', 'has_web_search' => 'boolean',
        'has_keyword_search' => 'boolean', 'default_task_duration_minutes' => 'integer',
        'daily_task_count' => 'integer', 'task_horizon_days' => 'integer',
        'schedule_days' => 'array', 'schedule_times' => 'array', 'is_active' => 'boolean',
        'task_creation_status' => 'array', 'task_engine_config' => 'array',
    ];

    public function image(): Attribute
    {
        return Attribute::make(get: fn () => asset('vendor/titan-nova/images/agent-default.png'));
    }

    public function imageUrl(): Attribute
    {
        return $this->image();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TitanNovaTask::class, 'agent_id');
    }

    public function channels(): array
    {
        return $this->channels ?? [];
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function planningHorizonDays(): int
    {
        return max(1, (int) ($this->task_horizon_days ?: 7));
    }

    public function defaultTaskDurationMinutes(): int
    {
        return max(5, min(1440, (int) ($this->default_task_duration_minutes ?: 30)));
    }

    public function canCreateTasks(): bool
    {
        return $this->isActive()
            && ! empty($this->selected_task_types)
            && ! empty($this->task_modes)
            && ! empty($this->schedule_days)
            && ! empty($this->schedule_times);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
