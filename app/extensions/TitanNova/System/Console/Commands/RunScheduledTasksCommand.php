<?php

namespace App\Extensions\TitanNova\System\Console\Commands;

use App\Extensions\TitanNova\System\Http\Controllers\TitanNovaController;
use App\Extensions\TitanNova\System\Models\TitanNovaTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunScheduledTasksCommand extends Command
{
    protected $signature = 'titan-nova:run-scheduled-tasks';
    protected $aliases = ['titan-nova:run-scheduled-tasks'];
    protected $description = 'Run Titan Nova tasks whose scheduled time has arrived';

    public function handle(): int
    {
        $this->info('Starting Titan Nova scheduled tasks...');
        $controller = app(TitanNovaController::class);
        $run = 0;
        $blocked = 0;

        TitanNovaTask::query()->readyToRun()->each(function (TitanNovaTask $task) use ($controller, &$run, &$blocked): void {
            if ($task->task_mode === TitanNovaTask::MODE_APPROVAL_REQUIRED && ! $task->approved_at) {
                $task->update(['status' => TitanNovaTask::STATUS_PENDING_APPROVAL]);
                $blocked++;
                return;
            }
            $controller->runTask($task->id, $task->user_id);
            $run++;
        });

        $this->info("Completed: {$run} task(s) dispatched; {$blocked} awaiting approval.");
        Log::info('Titan Nova scheduled task run finished', compact('run', 'blocked'));
        return self::SUCCESS;
    }
}
