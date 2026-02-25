<?php

use MobileStock\LaravelResilience\Contracts\RetryableException;
use MobileStock\LaravelResilience\Queue\Middleware\RetryableExceptionMiddleware;

it('should release job when retryable exception is thrown', function () {
    $middleware = new RetryableExceptionMiddleware();
    $job = Mockery::spy();
    $job->shouldReceive('attempts')->andReturn(1);
    $exception = new class extends Exception implements RetryableException {};
    $next = function () use ($exception) {
        throw $exception;
    };

    $middleware->handle($job, $next);

    $job->shouldHaveReceived('release')->once()->with(Mockery::type('int'));
});
