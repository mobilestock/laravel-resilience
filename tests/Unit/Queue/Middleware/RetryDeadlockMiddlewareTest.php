<?php

namespace Tests\Unit\Queue\Middleware;

use Illuminate\Database\QueryException;
use MobileStock\LaravelResilience\Queue\Middleware\RetryDeadlockMiddleware;
use Mockery;
use Tests\TestCase;

class RetryDeadlockMiddlewareTest extends TestCase
{
    private RetryDeadlockMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new RetryDeadlockMiddleware();
    }

    public function testShouldReleaseJobWhenDeadlockOccurs(): void
    {
        $job = Mockery::spy();
        $job->shouldReceive('attempts')->andReturn(1);

        $pdoException = new \PDOException();
        $pdoException->errorInfo = ['40001', 1213];

        $queryException = new QueryException('connection', 'sql', [], $pdoException);

        $next = function () use ($queryException) {
            throw $queryException;
        };

        $this->middleware->handle($job, $next);

        $job->shouldHaveReceived('release')->once()->with(Mockery::type('int'));
    }

    public function testShouldNotReleaseJobWhenQueryExceptionIsNotADeadlock(): void
    {
        $job = Mockery::spy();

        $pdoException = new \PDOException();
        $pdoException->errorInfo = ['HY000', 1234];

        $queryException = new QueryException('connection', 'sql', [], $pdoException);

        $next = function () use ($queryException) {
            throw $queryException;
        };

        expect(fn() => $this->middleware->handle($job, $next))->toThrow(QueryException::class);

        $job->shouldNotHaveReceived('release');
    }

    public function testShouldNotReleaseJobWhenOtherExceptionIsThrown(): void
    {
        $job = Mockery::spy();
        $exception = new \Exception('Normal exception');
        $next = function () use ($exception) {
            throw $exception;
        };

        expect(fn() => $this->middleware->handle($job, $next))->toThrow(\Exception::class, 'Normal exception');

        $job->shouldNotHaveReceived('release');
    }
}
