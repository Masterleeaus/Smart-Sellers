<?php

declare(strict_types=1);

namespace App\Extensions\TitanNova\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\TitanNova\System\Console\Commands\CreateAgentTasksCommand;
use App\Extensions\TitanNova\System\Console\Commands\RunScheduledTasksCommand;
use App\Extensions\TitanNova\System\Console\Commands\SeedDemoDataCommand;
use App\Extensions\TitanNova\System\Http\Controllers\TitanNovaController;
use App\Extensions\TitanNova\System\Models\TitanNovaAgent;
use App\Extensions\TitanNova\System\Policies\TitanNovaAgentPolicy;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TitanNovaServiceProvider extends ServiceProvider implements ExtensionRegisterKeyProviderInterface, UninstallExtensionServiceProviderInterface
{
    public function register(): void {}

    public function boot(Kernel $kernel): void
    {
        $this->registerTranslations()
            ->registerViews()
            ->registerRoutes()
            ->registerMigrations()
            ->registerPolicies()
            ->registerCommands()
            ->publishAssets();
    }

    protected function registerCommands(): static
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateAgentTasksCommand::class,
                RunScheduledTasksCommand::class,
                SeedDemoDataCommand::class,
            ]);

            // exec('php artisan titan-nova:create-tasks 1');
            // Schedule tasks
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('titan-nova:create-tasks')->everyTwoMinutes();
                $schedule->command('titan-nova:run-scheduled-tasks')->everyMinute();
            });
        }

        return $this;
    }

    protected function registerPolicies(): static
    {
        Gate::policy(TitanNovaAgent::class, TitanNovaAgentPolicy::class);

        return $this;
    }

    public function publishAssets(): static
    {
        $this->publishes([
            __DIR__ . '/../resources/assets/images' => public_path('vendor/titan-nova/images'),
            __DIR__ . '/../resources/assets/videos' => public_path('vendor/titan-nova/videos'),
        ], 'extension');

        return $this;
    }

    protected function registerTranslations(): static
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', $this->registerKey());

        return $this;
    }

    public function registerViews(): static
    {
        $this->loadViewsFrom([__DIR__ . '/../resources/views'], $this->registerKey());

        return $this;
    }

    public function registerMigrations(): static
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        return $this;
    }

    private function registerRoutes(): static
    {

        $this->router()
            ->group([
                'middleware' => ['web', 'auth'],
            ], function (Router $router) {
                $router
                    ->name('dashboard.user.titan-nova.agent.')
                    ->prefix('dashboard/user/titan-nova/agent')
                    ->group(function (Router $router) {
                        Route::get('', [TitanNovaController::class, 'index'])->name('index');
                        Route::get('task-items', [TitanNovaController::class, 'taskItems'])->name('task-items');
                        Route::get('create', [TitanNovaController::class, 'create'])->name('create');
                        Route::get('agents', [TitanNovaController::class, 'agents'])->name('agents');
                        Route::get('calendar', [TitanNovaController::class, 'calendar'])->name('calendar');
                        Route::get('tasks', [TitanNovaController::class, 'tasks'])->name('tasks');
                        Route::get('analytics', [TitanNovaController::class, 'analytics'])->name('analytics');
                        Route::post('', [TitanNovaController::class, 'store'])->name('store');
                        Route::get('{agent}/edit', [TitanNovaController::class, 'edit'])->name('edit');
                        Route::put('{agent}', [TitanNovaController::class, 'update'])->name('update');
                        Route::delete('{agent}', [TitanNovaController::class, 'destroy'])->name('destroy');

                        // Task management
                        Route::get('tasks/{task}/edit', [TitanNovaController::class, 'editTask'])->name('tasks.edit');
                        Route::delete('tasks/{task}/reject', [TitanNovaController::class, 'rejectTask'])->name('tasks.reject');
                        Route::post('tasks/{task}/duplicate', [TitanNovaController::class, 'duplicateTask'])->name('tasks.duplicate');
                        Route::post('tasks/{task}/update', [TitanNovaController::class, 'updateTask'])->name('tasks.update');
                        Route::post('tasks/{task}/run', [TitanNovaController::class, 'runTaskAjax'])->name('tasks.run');

                        // Task-engine wizard AJAX endpoints
                        Route::post('generate-task-types', [TitanNovaController::class, 'generateTaskTypes'])->name('generate-task-types');

                        // API endpoints
                        Route::get('api/pending-count', [TitanNovaController::class, 'getPendingTaskCount'])->name('api.pending-count');
                        Route::get('api/tasks', [TitanNovaController::class, 'getTasks'])->name('api.tasks');
                        Route::get('api/task-creation-status', [TitanNovaController::class, 'getTaskCreationStatus'])->name('api.creation-status');
                    });
            });

        // Generic channel callback endpoint
        $this->router()
            ->group([
                'middleware' => ['api'],
            ], function (Router $router) {
                Route::post('titan-nova/channel-webhook', [TitanNovaController::class, 'channelWebhook'])->name('dashboard.user.titan-nova.agent.channel-webhook');
            });

        return $this;
    }

    private function router(): Router|Route
    {
        return $this->app['router'];
    }

    public static function uninstall(): void {}

    public function registerKey(): string
    {
        return 'titan-nova';
    }
}
