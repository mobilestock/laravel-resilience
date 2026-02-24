<?php

namespace Tests\Unit\Queue\Middleware;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use MobileStock\LaravelResilience\Queue\Middleware\HttpClientRetryMiddleware;
use Mockery;
use Tests\TestCase;

class HttpClientRetryMiddlewareTest extends TestCase
{
    private HttpClientRetryMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new HttpClientRetryMiddleware();
    }

    public function testShouldReleaseJobWhen429ResponseExceptionIsThrown(): void
    {
        $job = Mockery::spy();
        $job->shouldReceive('attempts')->andReturn(2);
        $response = Http::fake([
            '*' => Http::response([], 429),
        ])->get('http://dummy.com');
        $exception = new RequestException($response);
        $next = function () use ($exception) {
            throw $exception;
        };

        $this->middleware->handle($job, $next);

        $job->shouldHaveReceived('release')
            ->once()
            ->with(Mockery::on(fn($delay) => $delay >= 4 && $delay <= 6));
    }

    public function testShouldReleaseJobUsingRetryAfterHeaderWhenPresent(): void
    {
        $job = Mockery::spy();
        $response = Http::fake([
            '*' => Http::response([], 429, ['Retry-After' => '60']),
        ])->get('http://dummy.com');
        $exception = new RequestException($response);
        $next = function () use ($exception) {
            throw $exception;
        };

        $this->middleware->handle($job, $next);

        $job->shouldHaveReceived('release')->once()->with(60);
    }

    public function testShouldReleaseJobUsingRetryAfterHeaderDateWhenPresent(): void
    {
        $job = Mockery::spy();
        $retryDate = (new \DateTimeImmutable('+60 seconds'))->format(\DateTimeInterface::RFC1123);
        $response = Http::fake([
            '*' => Http::response([], 429, ['Retry-After' => $retryDate]),
        ])->get('http://dummy.com');
        $exception = new RequestException($response);
        $next = function () use ($exception) {
            throw $exception;
        };

        $this->middleware->handle($job, $next);

        $job->shouldHaveReceived('release')
            ->once()
            ->with(Mockery::on(fn($delay) => $delay >= 59 && $delay <= 61));
    }

    public function testShouldRethrowExceptionWhenNon429ResponseIsReceived(): void
    {
        $job = Mockery::spy();
        $response = Http::fake([
            '*' => Http::response([], 500),
        ])->get('http://dummy.com');
        $exception = new RequestException($response);
        $next = function () use ($exception) {
            throw $exception;
        };

        expect(fn() => $this->middleware->handle($job, $next))->toThrow(RequestException::class);

        $job->shouldNotHaveReceived('fail');
    }
}
