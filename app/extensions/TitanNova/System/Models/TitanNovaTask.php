<?php

namespace App\Extensions\TitanNova\System\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TitanNovaTask extends Model
{
    use SoftDeletes;

    protected $table = 'ext_titan_nova_tasks';

    protected $fillable = [
        'user_id', 'agent_id', 'task_type', 'task_mode', 'priority', 'duration_minutes',
        'title', 'instructions', 'thumbnail', 'labels', 'categories', 'status',
        'scheduled_at', 'approved_at', 'channel', 'channel_payload', 'channel_receipt',
        'started_at', 'completed_at', 'failed_at', 'failure_reason',
    ];

    protected $casts = [
        'labels' => 'array', 'categories' => 'array', 'scheduled_at' => 'datetime',
        'approved_at' => 'datetime', 'duration_minutes' => 'integer',
        'channel_payload' => 'array', 'channel_receipt' => 'array',
        'started_at' => 'datetime', 'completed_at' => 'datetime', 'failed_at' => 'datetime',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const MODE_MANUAL = 'manual';
    public const MODE_AI_ASSISTED = 'ai_assisted';
    public const MODE_AUTOMATED = 'automated';
    public const MODE_APPROVAL_REQUIRED = 'approval_required';
    public const MODE_EXTERNAL_WORKFLOW = 'external_workflow';

    public function agent(): BelongsTo
    {
        return $this->belongsTo(TitanNovaAgent::class, 'agent_id');
    }

    public function taskName(): Attribute
    {
        return Attribute::make(get: fn () => $this->title, set: fn (string $value) => ['title' => $value]);
    }

    public function isDraft(): bool { return $this->status === self::STATUS_DRAFT; }
    public function isScheduled(): bool { return $this->status === self::STATUS_SCHEDULED; }
    public function isRunning(): bool { return $this->status === self::STATUS_RUNNING; }
    public function isCompleted(): bool { return $this->status === self::STATUS_COMPLETED; }
    public function isFailed(): bool { return $this->status === self::STATUS_FAILED; }

    public function canBeApproved(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING_APPROVAL], true);
    }

    public function canBeScheduled(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_APPROVED], true);
    }

    public function canRun(): bool
    {
        return $this->status === self::STATUS_SCHEDULED && $this->scheduled_at?->isPast();
    }

    public function markAsScheduled(DateTimeInterface $scheduledAt): self
    {
        $this->update(['status' => self::STATUS_SCHEDULED, 'scheduled_at' => $scheduledAt]);
        return $this;
    }

    public function markAsRunning(): self
    {
        $this->update(['status' => self::STATUS_RUNNING, 'started_at' => now()]);
        return $this;
    }

    public function markAsCompleted(array $receipt = []): self
    {
        $this->update([
            'status' => self::STATUS_COMPLETED, 'completed_at' => now(),
            'channel_receipt' => $receipt, 'failure_reason' => null, 'failed_at' => null,
        ]);
        return $this;
    }

    public function markAsFailed(string $reason): self
    {
        $this->update(['status' => self::STATUS_FAILED, 'failed_at' => now(), 'failure_reason' => $reason]);
        return $this;
    }

    public function scopeScheduled($query) { return $query->where('status', self::STATUS_SCHEDULED); }
    public function scopeCompleted($query) { return $query->where('status', self::STATUS_COMPLETED); }
    public function scopeFailed($query) { return $query->where('status', self::STATUS_FAILED); }
    public function scopeReadyToRun($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)->where('scheduled_at', '<=', now());
    }
    public function scopeForAgent($query, int $agentId) { return $query->where('agent_id', $agentId); }

    public static function getStatusArray(): array
    {
        return [
            self::STATUS_DRAFT => __('Draft'), self::STATUS_PENDING_APPROVAL => __('Pending Approval'),
            self::STATUS_APPROVED => __('Approved'), self::STATUS_SCHEDULED => __('Scheduled'),
            self::STATUS_RUNNING => __('Running'), self::STATUS_COMPLETED => __('Completed'),
            self::STATUS_FAILED => __('Failed'), self::STATUS_CANCELLED => __('Cancelled'),
        ];
    }
}
