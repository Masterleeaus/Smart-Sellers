<?php

namespace App\Extensions\TitanNova\System\Notifications;

use App\Extensions\TitanNova\System\Models\TitanNovaAgent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class TaskCreationCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected TitanNovaAgent $agent,
        protected int $generatedCount,
        protected int $failedCount = 0,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    protected function payload(): array
    {
        return [
            'agent_id' => $this->agent->id,
            'agent_name' => $this->agent->name,
            'created_count' => $this->generatedCount,
            'created_count' => $this->generatedCount,
            'failed_count' => $this->failedCount,
            'message' => $this->message(),
            'action_url' => route('dashboard.user.titan-nova.agent.index'),
            'type' => 'task_creation_completed',
        ];
    }

    protected function message(): string
    {
        if ($this->failedCount > 0) {
            return "Task creation completed for '{$this->agent->name}': {$this->generatedCount} created, {$this->failedCount} failed";
        }

        return "Task creation completed for '{$this->agent->name}': {$this->generatedCount} tasks ready";
    }
}
