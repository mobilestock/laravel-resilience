<?php

namespace Tests\Unit\Queue\Middleware;

use MobileStock\LaravelResilience\Contracts\RetryableException;
use MobileStock\LaravelResilience\Queue\Middleware\RetryableExceptionMiddleware;
use Mockery;
use Tests\TestCase;

class RetryableExceptionMiddlewareTest extends TestCase
{
    private RetryableExceptionMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new RetryableExceptionMiddleware();
    }

    public function testShouldReleaseJobWhenRetryableExceptionIsThrown(): void
    {
        $job = Mockery::spy();
        $job->shouldReceive('attempts')->andReturn(1);
        $exception = new class extends \Exception implements RetryableException {};
        $next = function () use ($exception) {
            throw $exception;
        };

        $this->middleware->handle($job, $next);

        $job->shouldHaveReceived('release')->once()->with(Mockery::type('int'));
    }

    public function testShouldFailJobWhenNonRetryableExceptionIsThrown(): void
    {
        $job = Mockery::spy();
        $exception = new \Exception('Normal exception');
        $next = function () use ($exception) {
            throw $exception;
        };

        expect(fn() => $this->middleware->handle($job, $next))->toThrow(\Exception::class, 'Normal exception');

        $job->shouldNotHaveReceived('fail');
    }
}
