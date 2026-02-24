<?php

namespace MobileStock\LaravelResilience\Queue\Middleware;

use Throwable;

class FailJobOnExceptionMiddleware
{
    public function handle(object $job, callable $next): void
    {
        try {
            $next($job);
        } catch (Throwable $exception) {
            $job->fail($exception);

            throw $exception;
        }
    }
}
