<?php

namespace MobileStock\LaravelResilience\Providers;

use Illuminate\Support\ServiceProvider;

class ResilienceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/resilience.php' => config_path('resilience.php'),
            ], 'resilience-config');
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/resilience.php', 'resilience');
    }
}
