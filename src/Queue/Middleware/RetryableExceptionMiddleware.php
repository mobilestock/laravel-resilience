<?php

namespace MobileStock\LaravelResilience\Queue\Middleware;

use MobileStock\LaravelResilience\Contracts\RetryableException;
use MobileStock\LaravelResilience\Queue\Middleware\Concerns\CalculatesBackoff;

class RetryableExceptionMiddleware
{
    use CalculatesBackoff;

    public function handle(object $job, callable $next): void
    {
        try {
            $next($job);
        } catch (RetryableException) {
            $this->releaseWithBackoff($job);
        }
    }
}
