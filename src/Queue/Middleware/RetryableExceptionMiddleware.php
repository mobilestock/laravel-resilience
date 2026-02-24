<?php

namespace MobileStock\LaravelResilience\Queue\Middleware;

use MobileStock\LaravelResilience\Contracts\RetryableException;
use MobileStock\LaravelResilience\Queue\Middleware\Concerns\CalculatesBackoff;
use Throwable;

class RetryableExceptionMiddleware
{
    use CalculatesBackoff;

    public function handle(object $job, callable $next): void
    {
        try {
            $next($job);
        } catch (Throwable $exception) {
            if ($exception instanceof RetryableException) {
                $job->release($this->calculateBackoff($job->attempts()));

                return;
            }

            throw $exception;
        }
    }
}
