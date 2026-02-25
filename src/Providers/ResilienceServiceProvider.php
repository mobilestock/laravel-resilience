<?php

namespace MobileStock\LaravelResilience\Providers;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use MobileStock\LaravelResilience\Console\ListenCommand;
use MobileStock\LaravelResilience\Console\WorkCommand;
use Illuminate\Queue\Console\ListenCommand as LaravelListenCommand;
use Illuminate\Queue\Console\WorkCommand as LaravelWorkCommand;

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

            $this->app->extend(LaravelWorkCommand::class, function (
                LaravelWorkCommand $command,
                Application $app
            ): WorkCommand {
                return new WorkCommand($app['queue.worker'], $app['cache.store']);
            });

            $this->app->extend(LaravelListenCommand::class, function (
                LaravelListenCommand $command,
                Application $app
            ): ListenCommand {
                return new ListenCommand($app['queue.listener']);
            });
        }

        $bus->pipeThrough($config->get('resilience.middlewares'));
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/resilience.php', 'resilience');
    }
}
