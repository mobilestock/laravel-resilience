<?php

namespace Tests\Unit\Queue\Middleware;

use MobileStock\LaravelResilience\Queue\Middleware\FailJobOnExceptionMiddleware;
use Mockery;
use Tests\TestCase;
use Throwable;

class FailJobOnExceptionMiddlewareTest extends TestCase
{
    private FailJobOnExceptionMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new FailJobOnExceptionMiddleware();
    }

    public function testShouldFailJobWhenExceptionIsThrown(): void
    {
        $job = Mockery::spy();
        $exception = new \Exception('Normal exception');
        $next = function () use ($exception) {
            throw $exception;
        };

        expect(fn() => $this->middleware->handle($job, $next))->toThrow(\Exception::class, 'Normal exception');

        $job->shouldHaveReceived('fail')
            ->once()
            ->with(Mockery::type(Throwable::class));
    }
}
