<?php

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
