<?php

namespace Tests\Unit\Queue\Middleware;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use MobileStock\LaravelResilience\Contracts\RetryableException;
use MobileStock\LaravelResilience\Queue\Middleware\RetriesWithBackoff;
use Mockery;
use Tests\TestCase;
use Throwable;

class RetriesWithBackoffTest extends TestCase
{
    private RetriesWithBackoff $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new RetriesWithBackoff();
    }

    public function testShouldReleaseJobWithBackoffWhenProxyReleaseIsCalled(): void
    {
        $job = Mockery::mock();
        $job->shouldReceive('attempts')->andReturn(1);
        $job->shouldReceive('release')
            ->once()
            ->with(Mockery::on(fn($delay) => $delay >= 2 && $delay <= 3));

        $next = function ($jobProxy) {
            $jobProxy->release();
        };

        $this->middleware->handle($job, $next);
    }

    public function testShouldDelegateToWrappedJobWhenUsingProxy(): void
    {
        $job = new class {
            public string $foo = 'bar';
            public function baz()
            {
                return 'qux';
            }
            public function attempts()
            {
                return 1;
            }
            public function release($delay)
            {
            }
        };

        $next = function ($jobProxy) {
            expect($jobProxy->foo)->toBe('bar');
            expect($jobProxy->baz())->toBe('qux');
        };

        $this->middleware->handle($job, $next);
    }

    public function testShouldReleaseJobWhenRetryableExceptionIsThrown(): void
    {
        $job = Mockery::mock();
        $job->shouldReceive('attempts')->andReturn(1);
        $job->shouldReceive('release')->once()->with(Mockery::type('int'));

        $exception = new class extends \Exception implements RetryableException {};

        $next = function () use ($exception) {
            throw $exception;
        };

        $this->middleware->handle($job, $next);
    }

    public function testShouldReleaseJobWhen429ResponseExceptionIsThrown(): void
    {
        $job = Mockery::mock();
        $job->shouldReceive('attempts')->andReturn(2);
        $job->shouldReceive('release')
            ->once()
            ->with(Mockery::on(fn($delay) => $delay >= 4 && $delay <= 6));

        $response = Http::fake([
            '*' => Http::response([], 429),
        ])->get('http://dummy.com');

        $exception = new RequestException($response);

        $next = function () use ($exception) {
            throw $exception;
        };

        $this->middleware->handle($job, $next);
    }

    public function testShouldFailJobWhenNonRetryableExceptionIsThrown(): void
    {
        $job = Mockery::mock();
        $job->shouldReceive('fail')
            ->once()
            ->with(Mockery::type(Throwable::class));
        $job->shouldNotReceive('release');

        $exception = new \Exception('Normal exception');

        $next = function () use ($exception) {
            throw $exception;
        };

        $this->middleware->handle($job, $next);
    }
}
