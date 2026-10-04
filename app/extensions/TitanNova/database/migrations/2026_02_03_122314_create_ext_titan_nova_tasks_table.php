<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ext_titan_nova_tasks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('agent_id')->index();
            $table->string('task_type')->nullable()->index();
            $table->string('task_mode')->default('manual')->index();
            $table->string('priority')->default('normal')->index();
            $table->unsignedInteger('duration_minutes')->default(30);
            $table->text('title');
            $table->longText('instructions')->nullable();
            $table->text('thumbnail')->nullable();
            $table->json('labels')->nullable();
            $table->json('categories')->nullable();
            $table->enum('status', ['draft','pending_approval','approved','scheduled','running','completed','failed','cancelled'])->default('draft')->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->string('channel')->default('internal')->index();
            $table->json('channel_payload')->nullable();
            $table->json('channel_receipt')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['agent_id','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('ext_titan_nova_tasks'); }
};
