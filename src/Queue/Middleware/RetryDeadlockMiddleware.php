<?php

namespace MobileStock\LaravelResilience\Queue\Middleware;

use MobileStock\LaravelResilience\Queue\Middleware\Concerns\CalculatesBackoff;

class RetryDeadlockMiddleware
{
    use CalculatesBackoff;

    public function handle(object $job, callable $next): void
    {
        $next($job);
    }
}
