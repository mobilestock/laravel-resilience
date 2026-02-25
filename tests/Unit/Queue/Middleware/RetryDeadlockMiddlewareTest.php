<?php

use Illuminate\Database\QueryException;
use MobileStock\LaravelResilience\Queue\Middleware\RetryDeadlockMiddleware;
use Exception;

it('should release job when deadlock occurs', function () {
    $middleware = new RetryDeadlockMiddleware();
    $job = Mockery::spy();
    $job->shouldReceive('attempts')->andReturn(1);
    $pdoException = new PDOException();
    $pdoException->errorInfo = ['40001', 1213];
    $queryException = new QueryException('connection', 'sql', [], $pdoException);
    $next = function () use ($queryException) {
        throw $queryException;
    };

    $middleware->handle($job, $next);

    $job->shouldHaveReceived('release')->once()->with(Mockery::type('int'));
});

it('should not release job when query exception is not a deadlock', function () {
    $middleware = new RetryDeadlockMiddleware();
    $job = Mockery::spy();
    $pdoException = new PDOException();
    $pdoException->errorInfo = ['HY000', 1234];
    $queryException = new QueryException('connection', 'sql', [], $pdoException);
    $next = function () use ($queryException) {
        throw $queryException;
    };

    $call = fn() => $middleware->handle($job, $next);

    expect($call)->toThrow(QueryException::class);
    $job->shouldNotHaveReceived('release');
});

it('should not release job when other exception is thrown', function () {
    $middleware = new RetryDeadlockMiddleware();
    $job = Mockery::spy();
    $exception = new Exception('Normal exception');
    $next = function () use ($exception) {
        throw $exception;
    };

    $call = fn() => $middleware->handle($job, $next);

    expect($call)->toThrow(Exception::class, 'Normal exception');
    $job->shouldNotHaveReceived('release');
});
