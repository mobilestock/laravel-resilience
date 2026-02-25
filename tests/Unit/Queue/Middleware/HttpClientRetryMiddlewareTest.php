<?php

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use MobileStock\LaravelResilience\Queue\Middleware\HttpClientRetryMiddleware;

it('should release job when 429 response exception is thrown', function () {
    $middleware = new HttpClientRetryMiddleware();
    $job = Mockery::spy();
    $job->shouldReceive('attempts')->andReturn(2);
    $response = Http::fake([
        '*' => Http::response([], 429),
    ])->get('http://dummy.com');
    $exception = new RequestException($response);
    $next = function () use ($exception) {
        throw $exception;
    };

    $middleware->handle($job, $next);

    $job->shouldHaveReceived('release')
        ->once()
        ->with(Mockery::on(fn($delay) => $delay >= 4 && $delay <= 6));
});

it('should release job using retry-after header when present', function () {
    $middleware = new HttpClientRetryMiddleware();
    $job = Mockery::spy();
    $response = Http::fake([
        '*' => Http::response([], 429, ['Retry-After' => '60']),
    ])->get('http://dummy.com');
    $exception = new RequestException($response);
    $next = function () use ($exception) {
        throw $exception;
    };

    $middleware->handle($job, $next);

    $job->shouldHaveReceived('release')->once()->with(60);
});

it('should release job using retry-after header date when present', function () {
    $middleware = new HttpClientRetryMiddleware();
    $job = Mockery::spy();
    $retryDate = (new DateTimeImmutable('+60 seconds'))->format(DateTimeInterface::RFC1123);
    $response = Http::fake([
        '*' => Http::response([], 429, ['Retry-After' => $retryDate]),
    ])->get('http://dummy.com');
    $exception = new RequestException($response);
    $next = function () use ($exception) {
        throw $exception;
    };

    $middleware->handle($job, $next);

    $job->shouldHaveReceived('release')
        ->once()
        ->with(Mockery::on(fn($delay) => $delay >= 59 && $delay <= 61));
});

it('should rethrow exception when non 429 response is received', function () {
    $middleware = new HttpClientRetryMiddleware();
    $job = Mockery::spy();
    $response = Http::fake([
        '*' => Http::response([], 500),
    ])->get('http://dummy.com');
    $exception = new RequestException($response);
    $next = function () use ($exception) {
        throw $exception;
    };

    $call = fn() => $middleware->handle($job, $next);

    expect($call)->toThrow(RequestException::class);
    $job->shouldNotHaveReceived('fail');
});

it('should release job with backoff when retry-after header date is invalid', function () {
    $middleware = new HttpClientRetryMiddleware();
    $job = Mockery::spy();
    $job->shouldReceive('attempts')->andReturn(1);
    $response = Http::fake([
        '*' => Http::response([], 429, ['Retry-After' => 'invalid-date']),
    ])->get('http://dummy.com');
    $exception = new RequestException($response);
    $next = function () use ($exception) {
        throw $exception;
    };

    $middleware->handle($job, $next);

    $job->shouldHaveReceived('release')
        ->once()
        ->with(Mockery::on(fn($delay) => $delay >= 2 && $delay <= 4));
});
