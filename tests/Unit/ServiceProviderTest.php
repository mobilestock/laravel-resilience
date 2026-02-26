<?php

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\Console\ListenCommand as LaravelListenCommand;
use Illuminate\Queue\Console\WorkCommand as LaravelWorkCommand;
use MobileStock\LaravelResilience\Providers\ResilienceServiceProvider;

test('service provider is registered', function () {
    expect(app()->getProvider(ResilienceServiceProvider::class))->not->toBeNull();
});

test('config is merged', function () {
    expect(config('resilience.middlewares'))->toBeArray();
});

it('should set default tries to 0 on queue commands', function (string $commandClass) {
    $command = app($commandClass);

    expect($command->getDefinition()->getOption('tries')->getDefault())->toBe(0);
})->with([[LaravelWorkCommand::class], [LaravelListenCommand::class]]);

it('should return early when not running in console', function () {
    $app = Mockery::mock(Application::class);
    $app->shouldReceive('runningInConsole')->once()->andReturn(false);

    $bus = Mockery::spy(Dispatcher::class);
    $config = Mockery::mock(Repository::class);

    $provider = new ResilienceServiceProvider($app);
    $provider->boot($bus, $config);

    expect(true)->toBeTrue();
    $bus->shouldNotHaveReceived('pipeThrough');
});
