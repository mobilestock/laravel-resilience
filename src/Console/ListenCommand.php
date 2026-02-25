<?php

namespace MobileStock\LaravelResilience\Console;

use Illuminate\Queue\Console\ListenCommand as LaravelListenCommand;
use Illuminate\Queue\Listener;

class ListenCommand extends LaravelListenCommand
{
    public function __construct(Listener $listener)
    {
        parent::__construct($listener);

        $this->getDefinition()->getOption('tries')->setDefault(0);
    }
}
