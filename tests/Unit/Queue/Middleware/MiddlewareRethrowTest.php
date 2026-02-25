<?php

use MobileStock\LaravelResilience\Queue\Middleware\HttpClientRetryMiddleware;
use MobileStock\LaravelResilience\Queue\Middleware\RetryableExceptionMiddleware;
use MobileStock\LaravelResilience\Queue\Middleware\RetryDeadlockMiddleware;

it('should re-throw exception and not release job when non-retryable exception is thrown', function (
    object $middleware
) {
    $job = Mockery::spy();
    $exception = new Exception('Normal exception');
    $next = function () use ($exception) {
        throw $exception;
    };

    $call = fn() => $middleware->handle($job, $next);

    expect($call)->toThrow(Exception::class, 'Normal exception');
    $job->shouldNotHaveReceived('release');
    $job->shouldNotHaveReceived('fail');
})->with([
    'HttpClientRetryMiddleware' => new HttpClientRetryMiddleware(),
    'RetryDeadlockMiddleware' => new RetryDeadlockMiddleware(),
    'RetryableExceptionMiddleware' => new RetryableExceptionMiddleware(),
]);
