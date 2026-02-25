<?php

namespace MobileStock\LaravelResilience\Console;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Queue\Console\WorkCommand as LaravelWorkCommand;
use Illuminate\Queue\Worker;

class WorkCommand extends LaravelWorkCommand
{
    public function __construct(Worker $worker, Cache $cache)
    {
        parent::__construct($worker, $cache);

        $this->getDefinition()->getOption('tries')->setDefault(0);
    }
}
