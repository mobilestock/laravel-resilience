<?php

use Illuminate\Queue\Console\ListenCommand as LaravelListenCommand;
use Illuminate\Queue\Console\WorkCommand as LaravelWorkCommand;
use MobileStock\LaravelResilience\Console\ListenCommand;
use MobileStock\LaravelResilience\Console\WorkCommand;

it('should extend console commands and set default tries to 0', function (
    string $laravelCommand,
    string $resilienceCommand
) {
    $command = app($laravelCommand);

    expect($command)
        ->toBeInstanceOf($resilienceCommand)
        ->and($command->getDefinition()->getOption('tries')->getDefault())
        ->toBe(0);
})->with([[LaravelWorkCommand::class, WorkCommand::class], [LaravelListenCommand::class, ListenCommand::class]]);
