<?php

namespace App\Extensions\TitanNova\System\Database\Seeders;

use App\Extensions\TitanNova\System\Models\TitanNovaAgent;
use App\Extensions\TitanNova\System\Models\TitanNovaTask;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class TitanNovaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->first();
        if (! $user) {
            Log::warning('Titan Nova demo seeder requires an existing user.');
            $this->command?->error('No users found. Please create a user first.');
            return;
        }

        $agent = TitanNovaAgent::query()->create([
            'user_id' => $user->id,
            'name' => 'Vertical Launch Agent',
            'task_type_options' => ['Source medical cleaning leads', 'Create staff training', 'Follow up pilot jobs', 'Collect feedback and payment'],
            'selected_task_types' => ['Source medical cleaning leads', 'Create staff training', 'Follow up pilot jobs'],
            'task_modes' => [TitanNovaTask::MODE_AI_ASSISTED, TitanNovaTask::MODE_APPROVAL_REQUIRED],
            'has_image' => false,
            'has_emoji' => true,
            'has_web_search' => true,
            'has_keyword_search' => true,
            'language' => 'en-AU',
            'default_task_duration_minutes' => '30',
            'default_task_duration_minutes' => 30,
            'priority' => 'high',
            'frequency' => 'weekly',
            'task_horizon_days' => 7,
            'daily_task_count' => 2,
            'schedule_days' => ['1', '3', '5'],
            'schedule_times' => [
                ['key' => 'morning', 'label' => 'Morning', 'start' => '08:00', 'end' => '12:00'],
                ['key' => 'noon', 'label' => 'Noon', 'start' => '12:00', 'end' => '16:00'],
            ],
            'is_active' => true,
            'task_creation_status' => ['status' => 'idle'],
            'task_engine_config' => ['demo' => true],
        ]);

        TitanNovaTask::query()->create([
            'user_id' => $user->id,
            'agent_id' => $agent->id,
            'title' => 'Research five medical-equipment cleaning prospects',
            'content' => '<p>Identify five qualified prospects, preserve source provenance, and prepare each for approval before outreach.</p>',
            'thumbnail' => '',
            'tags' => ['lead sourcing', 'medical cleaning'],
            'categories' => ['Vertical Launch'],
            'status' => TitanNovaTask::STATUS_SCHEDULED,
            'scheduled_at' => now()->addHour(),
            'task_type' => 'Source medical cleaning leads',
            'task_mode' => TitanNovaTask::MODE_APPROVAL_REQUIRED,
            'priority' => 'high',
            'duration_minutes' => 60,
            'channel' => 'titan_action',
            'channel_payload' => [
                'capability' => 'titan-maps-intelligence.search.businesses',
                'payload' => ['query' => 'medical equipment cleaning prospects'],
            ],
        ]);

        $this->command?->info('Titan Nova demo agent and task created.');
    }
}
