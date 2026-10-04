<?php

namespace App\Extensions\TitanNova\System\Notifications;

use App\Extensions\TitanNova\System\Models\TitanNovaTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected TitanNovaTask $task,
        protected bool $isSuccess = true,
        protected ?string $errorMessage = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->isSuccess) {
            return (new MailMessage)
                ->subject('Titan Nova Task Completed')
                ->greeting('Task completed')
                ->line('The scheduled task completed successfully.')
                ->line('Agent: ' . $this->task->agent->name)
                ->line('Completed at: ' . optional($this->task->completed_at)->format('M d, Y \\a\\t h:i A'))
                ->action('View Task', route('dashboard.user.titan-nova.agent.tasks.edit', $this->task));
        }

        return (new MailMessage)
            ->subject('Titan Nova Task Failed')
            ->greeting('Task execution failed')
            ->error()
            ->line('Agent: ' . $this->task->agent->name)
            ->line('Error: ' . ($this->errorMessage ?? 'Unknown error'))
            ->action('View Task', route('dashboard.user.titan-nova.agent.tasks.edit', $this->task));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'agent_id' => $this->task->agent_id,
            'agent_name' => $this->task->agent->name,
            'completed_at' => $this->task->completed_at?->toIso8601String(),
            'is_success' => $this->isSuccess,
            'message' => $this->isSuccess
                ? "Task for '{$this->task->agent->name}' completed successfully"
                : "Task for '{$this->task->agent->name}' failed",
            'error_message' => $this->errorMessage,
            'action_url' => route('dashboard.user.titan-nova.agent.tasks.edit', $this->task),
        ];
    }
}
