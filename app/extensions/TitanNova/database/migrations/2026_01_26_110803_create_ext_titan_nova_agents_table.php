<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ext_titan_nova_agents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('name');
            $table->json('task_type_options')->nullable();
            $table->json('selected_task_types')->nullable();
            $table->json('task_modes')->nullable();
            $table->json('channels')->nullable();
            $table->boolean('has_image')->default(false);
            $table->boolean('has_emoji')->default(false);
            $table->boolean('has_web_search')->default(false);
            $table->boolean('has_keyword_search')->default(false);
            $table->string('language')->default('en');
            $table->unsignedInteger('default_task_duration_minutes')->default(30);
            $table->string('priority')->default('normal');
            $table->string('frequency')->default('weekly');
            $table->unsignedInteger('daily_task_count')->default(1);
            $table->unsignedInteger('task_horizon_days')->default(7);
            $table->json('schedule_days')->nullable();
            $table->json('schedule_times')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('task_creation_status')->nullable();
            $table->json('task_engine_config')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('ext_titan_nova_agents'); }
};
