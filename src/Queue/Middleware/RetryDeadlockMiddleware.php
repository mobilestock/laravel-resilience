<?php

namespace MobileStock\LaravelResilience\Queue\Middleware;

use Illuminate\Database\QueryException;
use MobileStock\LaravelResilience\Queue\Middleware\Concerns\CalculatesBackoff;

class RetryDeadlockMiddleware
{
    use CalculatesBackoff;

    protected const string SQLSTATE_DEADLOCK = '40001';
    protected const int MYSQL_ERROR_DEADLOCK = 1213;

    public function handle(object $job, callable $next): void
    {
        try {
            $next($job);
        } catch (QueryException $exception) {
            if (
                ($exception->errorInfo[0] ?? null) === self::SQLSTATE_DEADLOCK &&
                ($exception->errorInfo[1] ?? null) === self::MYSQL_ERROR_DEADLOCK
            ) {
                $job->release($this->calculateBackoff($job->attempts()));

                return;
            }

            throw $exception;
        }
    }
}
