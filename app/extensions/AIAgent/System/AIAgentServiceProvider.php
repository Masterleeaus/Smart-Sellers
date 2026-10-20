<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System;

use TitanAI\Hybrid\ActionsDiscovered;
use TitanAI\Hybrid\ConnectorsDiscovered;
use TitanAI\Hybrid\ExtensionBooted;
use TitanAI\Hybrid\Events\TitanAIEventBus;
use TitanAI\Hybrid\Registries\UnifiedRegistry;
use TitanAI\Hybrid\SkillsDiscovered;
use TitanAI\Hybrid\TitanAIServiceProvider;

use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\AIAgent\System\Actions\AiCallAction;
use App\Extensions\AIAgent\System\Actions\GenerateReportAction;
use App\Extensions\AIAgent\System\Actions\PathAction;
use App\Extensions\AIAgent\System\Actions\SendMessageAction;
use App\Extensions\AIAgent\System\Actions\UnifiedActionAdapter;
use App\Extensions\AIAgent\System\Connectors\ConnectorRegistry;
use App\Extensions\AIAgent\System\Connectors\MagicAiConnector;
use App\Extensions\AIAgent\System\Connectors\TelegramConnector;
use App\Extensions\AIAgent\System\Console\Commands\MigrateActionsToStepsCommand;
use App\Extensions\AIAgent\System\Console\Commands\ProcessWorkflowTriggersCommand;
use App\Extensions\AIAgent\System\Engine\AIAgentActionRegistry;
use App\Extensions\AIAgent\System\Enums\ChannelEnum;
use App\Extensions\AIAgent\System\Formatters\MessageFormatter;
use App\Extensions\AIAgent\System\Formatters\TelegramFormatter;
use App\Extensions\AIAgent\System\Http\Controllers\AIAgentDashboardController;
use App\Extensions\AIAgent\System\Http\Controllers\AIAgentSettingController;
use App\Extensions\AIAgent\System\Http\Controllers\ChannelController;
use App\Extensions\AIAgent\System\Http\Controllers\CopilotController;
use App\Extensions\AIAgent\System\Http\Controllers\KnowledgeSourceController;
use App\Extensions\AIAgent\System\Http\Controllers\MemoryController;
use App\Extensions\AIAgent\System\Http\Controllers\MessageController;
use App\Extensions\AIAgent\System\Http\Controllers\Webhook\GenericWebhookController;
use App\Extensions\AIAgent\System\Http\Controllers\Webhook\TelegramWebhookController;
use App\Extensions\AIAgent\System\Http\Controllers\WorkflowController;
use App\Extensions\AIAgent\System\Memory\MemoryRepository;
use App\Extensions\AIAgent\System\Models\AIAgentChannel;
use App\Extensions\AIAgent\System\Models\AIAgentMemory;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Policies\AIAgentChannelPolicy;
use App\Extensions\AIAgent\System\Policies\AIAgentMemoryPolicy;
use App\Extensions\AIAgent\System\Policies\AIAgentWorkflowPolicy;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AIAgentServiceProvider extends ServiceProvider implements UninstallExtensionServiceProviderInterface
{
    public function register(): void
    {
        $this->app->register(TitanAIServiceProvider::class);
        $this->registerConfig();
        $this->app->singleton(ConnectorRegistry::class);
        $this->app->singleton(AIAgentActionRegistry::class);
    }

    public function boot(Kernel $kernel): void
    {
        $this->registerTranslations()
            ->registerViews()
            ->registerRoutes()
            ->registerMigrations()
            ->publishAssets()
            ->registerCommand()
            ->registerPolicies()
            ->registerComponents()
            ->registerTelegramConnector()
            ->registerBuiltInActions();

        if ((bool) config('titanai.extensions.aiagent.enabled', true)) {
            if ((bool) config('titanai.extensions.aiagent.auto_register_to_unified_registry', true)) {
                $this->registerActionsToUnifiedRegistry();
            }
            if ((bool) config('titanai.extensions.aiagent.listen_to_events', true)) {
                $this->subscribeToTitanAIEvents();
            }
            $this->app->make(TitanAIEventBus::class)->dispatch(
                new ExtensionBooted('aiagent', [
                    'registry' => $this->app->make(UnifiedRegistry::class)->counts(),
                ]),
                'extension:aiagent:booted',
            );
        }
    }

    private function registerActionsToUnifiedRegistry(): void
    {
        $nativeRegistry = $this->app->make(AIAgentActionRegistry::class);

        $nativeRegistry->onRegistered(function (string $key, string $_actionClass): void {
            try {
                $nativeRegistry = $this->app->make(AIAgentActionRegistry::class);
                $nativeAction = $nativeRegistry->resolve($key);
                if (! $nativeAction instanceof \App\Extensions\AIAgent\System\Actions\Contracts\AIAgentActionInterface) {
                    throw new \UnexpectedValueException(
                        "AI Agent action [{$key}] does not implement the unified action metadata contract.",
                    );
                }

                $registry = $this->app->make(UnifiedRegistry::class);
                if (! $registry->hasAction($key)) {
                    $registry->registerAction($key, new UnifiedActionAdapter(
                        $key,
                        $nativeAction,
                        $this->app->make(TitanAIEventBus::class),
                    ));
                }

                if ((bool) config('titanai.extensions.aiagent.emit_discovery_events', true)) {
                    $metadata = $registry->actionMetadata();
                    $this->app->make(TitanAIEventBus::class)->dispatch(
                        new ActionsDiscovered($metadata, 'aiagent'),
                        'discovery:actions:aiagent:' . hash('sha256', json_encode($metadata, JSON_THROW_ON_ERROR)),
                    );
                }
            } catch (\Throwable $exception) {
                Log::warning('AI Agent action could not be mirrored to UnifiedRegistry.', [
                    'action' => $key,
                    'exception' => $exception,
                ]);
            }
        }, replay: true, listenerKey: 'titanai.unified.actions');
    }

    private function subscribeToTitanAIEvents(): void
    {
        $events = $this->app->make(TitanAIEventBus::class);
        $events->listenOnce('aiagent.skills-discovered', SkillsDiscovered::class, static function (SkillsDiscovered $event): void {
            Log::debug('AI Agent skill discovery snapshot updated.', [
                'source' => $event->source,
                'count' => count($event->skills),
            ]);
        });

        $events->listenOnce('aiagent.connectors-discovered', ConnectorsDiscovered::class, static function (ConnectorsDiscovered $event): void {
            Log::debug('AI Agent connector discovery snapshot updated.', [
                'source' => $event->source,
                'count' => count($event->connectors),
            ]);
        });
    }

    private function registerBuiltInActions(): static
    {
        $registry = $this->app->make(AIAgentActionRegistry::class);
        $registry->register('ai_call', AiCallAction::class);
        $registry->register('send_message', SendMessageAction::class);
        $registry->register('generate_report', GenerateReportAction::class);
        $registry->register('path', PathAction::class);

        return $this;
    }

    private function registerTelegramConnector(): static
    {
        $registry = $this->app->make(ConnectorRegistry::class);
        $registry->register(ChannelEnum::Telegram, TelegramConnector::class);
        $registry->register(ChannelEnum::MagicAi, MagicAiConnector::class);

        $formatter = $this->app->make(MessageFormatter::class);
        $formatter->registerFormatter(ChannelEnum::Telegram, $this->app->make(TelegramFormatter::class));

        return $this;
    }

    public function registerPolicies(): static
    {
        Gate::policy(AIAgentWorkflow::class, AIAgentWorkflowPolicy::class);
        Gate::policy(AIAgentChannel::class, AIAgentChannelPolicy::class);
        Gate::policy(AIAgentMemory::class, AIAgentMemoryPolicy::class);

        return $this;
    }

    public function registerCommand(): static
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessWorkflowTriggersCommand::class,
                MigrateActionsToStepsCommand::class,
            ]);

            $this->app->booted(function (): void {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('ai-agent:process-triggers')->everyMinute();
            });
        }

        return $this;
    }

    public function registerComponents(): static
    {
        return $this;
    }

    public function publishAssets(): static
    {
        $this->publishes([
            __DIR__ . '/../resources/assets/images' => public_path('vendor/ai-agent/images'),
        ], 'extension');

        return $this;
    }

    public function registerConfig(): static
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ai-agent.php', 'ai-agent');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'ai-agent');

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], 'ai-agent');

        return $this;
    }

    public function registerMigrations(): static
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        return $this;
    }

    private function registerRoutes(): static
    {
        // ── Webhook routes (no auth, but signature verified) ────────────────────
        $this->router()
            ->group([
                'middleware' => 'api',
                'prefix'     => 'api/ai-agent',
                'as'         => 'api.ai-agent.',
            ], function (Router $router): void {
                $router->post('telegram/{channel}/webhook', [TelegramWebhookController::class, 'handle'])
                    ->name('telegram.webhook');

                // Generic webhook - resolves by public ID, requires signature verification
                $router->post('webhook/{publicId}', [GenericWebhookController::class, 'handle'])
                    ->name('webhook');
            });

        // ── Authenticated dashboard routes ────────────────────────────────────
        $this->router()
            ->group([
                'middleware' => ['web', 'auth'],
                'prefix'     => 'dashboard/user/ai-agent',
                'as'         => 'dashboard.user.ai-agent.',
            ], function (Router $router): void {
                $router->get('', AIAgentDashboardController::class)->name('dashboard');

                // Workflows
                $router->post('workflows/generate-from-prompt', [WorkflowController::class, 'generateFromPrompt'])
                    ->name('workflows.generate-from-prompt');
                $router->get('workflows/available-actions', [WorkflowController::class, 'availableActions'])
                    ->name('workflows.available-actions');
                $router->get('workflows/available-models', [WorkflowController::class, 'availableModels'])
                    ->name('workflows.available-models');
                $router->get('workflows/available-social-media-agents', [WorkflowController::class, 'availableSocialMediaAgents'])
                    ->name('workflows.available-social-media-agents');
                $router->get('workflows/available-social-media-platforms', [WorkflowController::class, 'availableSocialMediaPlatforms'])
                    ->name('workflows.available-social-media-platforms');
                $router->resource('workflows', WorkflowController::class);
                $router->patch('workflows/{workflow}/toggle-status', [WorkflowController::class, 'toggleStatus'])
                    ->name('workflows.toggle-status');
                $router->post('workflows/{workflow}/avatar', [WorkflowController::class, 'uploadAvatar'])
                    ->name('workflows.upload-avatar');
                $router->get('workflows/{workflow}/runs', [WorkflowController::class, 'runs'])
                    ->name('workflows.runs');

                // Copilot
                $router->post('workflows/{workflow}/copilot/chat', [CopilotController::class, 'chat'])
                    ->name('workflows.copilot.chat');
                $router->get('workflows/{workflow}/copilot/history', [CopilotController::class, 'history'])
                    ->name('workflows.copilot.history');

                // Knowledge Sources
                $router->get('knowledge-sources', [KnowledgeSourceController::class, 'index'])
                    ->name('knowledge-sources.index');
                $router->post('knowledge-sources', [KnowledgeSourceController::class, 'store'])
                    ->name('knowledge-sources.store');
                $router->delete('knowledge-sources/{knowledgeSource}', [KnowledgeSourceController::class, 'destroy'])
                    ->name('knowledge-sources.destroy');

                // Channels
                $router->resource('channels', ChannelController::class)->except(['show', 'edit', 'update']);
                $router->post('channels/{channel}/refresh-webhook', [ChannelController::class, 'refreshWebhook'])
                    ->name('channels.refresh-webhook');
                $router->get('channels/{channel}/check-status', [ChannelController::class, 'checkStatus'])
                    ->name('channels.check-status');

                // Memory
                $router->get('memory', [MemoryController::class, 'index'])->name('memory.index');
                $router->post('memory', [MemoryController::class, 'store'])->name('memory.store');
                $router->get('memory/{memory}/edit', [MemoryController::class, 'edit'])->name('memory.edit');
                $router->put('memory/{memory}', [MemoryController::class, 'update'])->name('memory.update');
                $router->delete('memory/{memory}', [MemoryController::class, 'destroy'])->name('memory.destroy');

                // Messages
                $router->get('messages', [MessageController::class, 'index'])->name('messages.index');
                $router->get('messages/conversations', [MessageController::class, 'conversations'])->name('messages.conversations');
                $router->get('messages/unread-count', [MessageController::class, 'unreadCount'])->name('messages.unread-count');
                $router->post('messages/mark-seen', [MessageController::class, 'markSeen'])->name('messages.mark-seen');
                $router->post('messages/send', [MessageController::class, 'send'])->name('messages.send');
                $router->get('messages/{conversation}/thread', [MessageController::class, 'thread'])->name('messages.thread');
                $router->post('messages/{conversation}/reply', [MessageController::class, 'reply'])->name('messages.reply');
                $router->post('messages/{conversation}/pin', [MessageController::class, 'pin'])->name('messages.pin');
                $router->post('messages/{conversation}/close', [MessageController::class, 'close'])->name('messages.close');
                $router->delete('messages/{conversation}', [MessageController::class, 'destroy'])->name('messages.destroy');
                $router->get('messages/{conversation}/related', [MessageController::class, 'relatedHistory'])->name('messages.related');
                $router->get('messages/{conversation}/export', [MessageController::class, 'export'])->name('messages.export');
            });

        // ── Admin routes ──────────────────────────────────────────────────────
        $this->router()
            ->group([
                'middleware' => ['web', 'auth', 'admin'],
                'prefix'     => 'dashboard/admin/ai-agent',
                'as'         => 'dashboard.admin.ai-agent.',
            ], function (Router $router): void {
                $router->get('/settings', [AIAgentSettingController::class, 'index'])->name('settings');
                $router->post('/settings', [AIAgentSettingController::class, 'update'])->name('settings.update');
            });

        return $this;
    }

    private function router(): Router|Route
    {
        return $this->app['router'];
    }

    public static function uninstall(): void
    {
        app(MemoryRepository::class); // Ensure it's resolved
        // Tables are dropped via migration rollback; no additional cleanup needed here.
    }
}
