<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\LoggerContract;
use App\Services\Logger\LaravelLogger;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for logging infrastructure.
 *
 * Registers the LoggerContract implementation into the service container
 * to enable dependency injection across all extensions.
 */
class LoggerServiceProvider extends ServiceProvider
{
    /**
     * Register the logging service.
     */
    public function register(): void
    {
        $this->app->singleton(LoggerContract::class, function ($app) {
            return new LaravelLogger($app->make('log'));
        });
    }

    /**
     * Bootstrap the logging service.
     */
    public function boot(): void
    {
        //
    }
}
