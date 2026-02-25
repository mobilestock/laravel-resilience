<?php

namespace MobileStock\LaravelResilience\Providers;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;

class ResilienceServiceProvider extends ServiceProvider
{
    public function boot(Dispatcher $bus, Repository $config): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [
                    __DIR__ . '/../../config/resilience.php' => $this->app->configPath('resilience.php'),
                ],
                'resilience-config'
            );
        }

        $bus->pipeThrough($config->get('resilience.middlewares'));
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/resilience.php', 'resilience');
    }
}
