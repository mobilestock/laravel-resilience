<?php

use MobileStock\LaravelResilience\Queue\Middleware\FailJobOnExceptionMiddleware;

it('should fail job when exception is thrown', function () {
    $middleware = new FailJobOnExceptionMiddleware();
    $job = Mockery::spy();
    $exception = new Exception('Normal exception');
    $next = function () use ($exception) {
        throw $exception;
    };

    $call = fn() => $middleware->handle($job, $next);

    expect($call)->toThrow(Exception::class, 'Normal exception');
    $job->shouldHaveReceived('fail')
        ->once()
        ->with(Mockery::type(Throwable::class));
});
