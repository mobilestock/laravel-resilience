<?php

namespace MobileStock\LaravelResilience\Providers;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
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

            foreach ([LaravelWorkCommand::class, LaravelListenCommand::class] as $command) {
                $this->app->extend($command, function ($command) {
                    $command->getDefinition()->getOption('tries')->setDefault(0);

                    return $command;
                });
            }
        }

        $bus->pipeThrough($config->get('resilience.middlewares'));
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/resilience.php', 'resilience');
    }
}
