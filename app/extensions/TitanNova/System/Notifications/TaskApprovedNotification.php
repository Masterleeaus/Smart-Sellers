<?php

namespace App\Extensions\TitanNova\System\Notifications;

use App\Extensions\TitanNova\System\Models\TitanNovaTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(protected TitanNovaTask $task) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Titan Nova Task Approved')
            ->greeting('Hello!')
            ->line('Your task has been approved and is ready for its scheduled run.')
            ->line('Agent: ' . $this->task->agent->name)
            ->line('Scheduled for: ' . optional($this->task->scheduled_at)->format('M d, Y \\a\\t h:i A'))
            ->action('View Task', route('dashboard.user.titan-nova.agent.tasks.edit', $this->task))
            ->line('Execution still follows the task mode, permissions and approval policy.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'agent_id' => $this->task->agent_id,
            'agent_name' => $this->task->agent->name,
            'scheduled_at' => $this->task->scheduled_at?->toIso8601String(),
            'message' => "Task for '{$this->task->agent->name}' has been approved",
            'action_url' => route('dashboard.user.titan-nova.agent.tasks.edit', $this->task),
        ];
    }
}
