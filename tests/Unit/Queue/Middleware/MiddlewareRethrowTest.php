<?php

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use MobileStock\LaravelResilience\Queue\Middleware\HttpClientRetryMiddleware;
use MobileStock\LaravelResilience\Queue\Middleware\RetryableExceptionMiddleware;
use MobileStock\LaravelResilience\Queue\Middleware\RetryDeadlockMiddleware;

it('should re-throw exception and not release job when non-retryable exception is thrown', function (
    object $middleware,
    Closure $exceptionFactory
) {
    $job = Mockery::spy();
    $exception = $exceptionFactory();
    $next = function () use ($exception) {
        throw $exception;
    };

    $call = fn() => $middleware->handle($job, $next);

    expect($call)->toThrow($exception::class, $exception->getMessage());
    $job->shouldNotHaveReceived('release');
    $job->shouldNotHaveReceived('fail');
})->with([
    'HttpClientRetryMiddleware' => [
        new HttpClientRetryMiddleware(),
        fn() => new RequestException(Http::fake(['*' => Http::response([], 500)])->get('http://dummy.com')),
    ],
    'RetryDeadlockMiddleware' => [
        new RetryDeadlockMiddleware(),
        fn() => new Illuminate\Database\QueryException('connection', 'sql', [], new PDOException('non-deadlock')),
    ],
    'RetryableExceptionMiddleware' => [new RetryableExceptionMiddleware(), fn() => new Exception('Normal exception')],
]);
