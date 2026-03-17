<?php

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use MobileStock\LaravelResilience\Queue\Middleware\HttpClientRetryMiddleware;
use Psr\Http\Message\RequestInterface;

it('should release the job when a retry is possible', function (int $attempts, array $headers, callable $assertion) {
    $middleware = new HttpClientRetryMiddleware();
    $job = Mockery::spy();
    $job->allows('attempts')->andReturn($attempts);
    $response = Http::fake([
        '*' => Http::response([], 429, $headers),
    ])->get('http://dummy.com');
    $exception = new RequestException($response);
    $next = function () use ($exception) {
        throw $exception;
    };

    $middleware->handle($job, $next);

    $assertion($job);
})->with([
    'without header (backoff)' => [
        2,
        [],
        fn($job) => $job
            ->shouldHaveReceived('release')
            ->once()
            ->with(Mockery::on(fn($delay) => $delay >= 4 && $delay <= 6)),
    ],
    'with numeric Retry-After header' => [
        1,
        ['Retry-After' => '60'],
        fn($job) => $job->shouldHaveReceived('release')->once()->with(60),
    ],
    'with date Retry-After header' => [
        1,
        ['Retry-After' => (new DateTimeImmutable('+60 seconds'))->format(DateTimeInterface::RFC1123)],
        fn($job) => $job
            ->shouldHaveReceived('release')
            ->once()
            ->with(Mockery::on(fn($delay) => $delay >= 59 && $delay <= 61)),
    ],
    'with invalid date header (backoff)' => [
        1,
        ['Retry-After' => 'invalid-date'],
        fn($job) => $job
            ->shouldHaveReceived('release')
            ->once()
            ->with(Mockery::on(fn($delay) => $delay >= 2 && $delay <= 4)),
    ],
    'with past date header (backoff)' => [
        1,
        ['Retry-After' => (new DateTimeImmutable('-60 seconds'))->format(DateTimeInterface::RFC1123)],
        fn($job) => $job
            ->shouldHaveReceived('release')
            ->once()
            ->with(Mockery::on(fn($delay) => $delay >= 2 && $delay <= 4)),
    ],
    'with zero header (backoff)' => [
        1,
        ['Retry-After' => '0'],
        fn($job) => $job
            ->shouldHaveReceived('release')
            ->once()
            ->with(Mockery::on(fn($delay) => $delay >= 2 && $delay <= 4)),
    ],
    'with negative header (backoff)' => [
        1,
        ['Retry-After' => '-60'],
        fn($job) => $job
            ->shouldHaveReceived('release')
            ->once()
            ->with(Mockery::on(fn($delay) => $delay >= 2 && $delay <= 4)),
    ],
]);

it('should release the job when RequestException is wrapped in another exception', function () {
    $middleware = new HttpClientRetryMiddleware();
    $job = Mockery::spy();
    $job->allows('attempts')->andReturn(1);
    $response = Http::fake([
        '*' => Http::response([], 429, ['Retry-After' => '30']),
    ])->get('http://dummy.com');
    $requestException = new RequestException($response);
    $wrappedException = new RuntimeException('Something went wrong', 0, $requestException);
    $next = function () use ($wrappedException) {
        throw $wrappedException;
    };

    $middleware->handle($job, $next);

    $job->shouldHaveReceived('release')->once()->with(30);
});

it('should release the job when ClientException is thrown directly', function () {
    $middleware = new HttpClientRetryMiddleware();
    $job = Mockery::spy();
    $job->allows('attempts')->andReturn(1);
    $guzzleRequest = Mockery::mock(RequestInterface::class);
    $guzzleResponse = new GuzzleResponse(429, ['Retry-After' => '45']);
    $exception = new ClientException('Too Many Requests', $guzzleRequest, $guzzleResponse);
    $next = function () use ($exception) {
        throw $exception;
    };

    $middleware->handle($job, $next);

    $job->shouldHaveReceived('release')->once()->with(45);
});

it('should release the job when ClientException is wrapped in another exception', function () {
    $middleware = new HttpClientRetryMiddleware();
    $job = Mockery::spy();
    $job->allows('attempts')->andReturn(2);
    $guzzleRequest = Mockery::mock(RequestInterface::class);
    $guzzleResponse = new GuzzleResponse(429);
    $clientException = new ClientException('Too Many Requests', $guzzleRequest, $guzzleResponse);
    $wrappedException = new RuntimeException('Wrapped', 0, $clientException);
    $next = function () use ($wrappedException) {
        throw $wrappedException;
    };

    $middleware->handle($job, $next);

    $job->shouldHaveReceived('release')
        ->once()
        ->with(Mockery::on(fn($delay) => $delay >= 4 && $delay <= 6));
});

it('should rethrow when no HTTP exception is found in the chain', function () {
    $middleware = new HttpClientRetryMiddleware();
    $job = Mockery::spy();
    $exception = new RuntimeException('Not an HTTP exception');
    $next = function () use ($exception) {
        throw $exception;
    };

    expect(fn() => $middleware->handle($job, $next))->toThrow(RuntimeException::class, 'Not an HTTP exception');
});

it('should rethrow when wrapped HTTP exception is not 429', function () {
    $middleware = new HttpClientRetryMiddleware();
    $job = Mockery::spy();
    $response = Http::fake([
        '*' => Http::response([], 500),
    ])->get('http://dummy.com');
    $requestException = new RequestException($response);
    $wrappedException = new RuntimeException('Wrapped', 0, $requestException);
    $next = function () use ($wrappedException) {
        throw $wrappedException;
    };

    expect(fn() => $middleware->handle($job, $next))->toThrow(RuntimeException::class, 'Wrapped');
});

it('should find RequestException nested multiple levels deep', function () {
    $middleware = new HttpClientRetryMiddleware();
    $job = Mockery::spy();
    $job->allows('attempts')->andReturn(1);
    $response = Http::fake([
        '*' => Http::response([], 429, ['Retry-After' => '15']),
    ])->get('http://dummy.com');
    $requestException = new RequestException($response);
    $level1 = new RuntimeException('Level 1', 0, $requestException);
    $level2 = new RuntimeException('Level 2', 0, $level1);
    $next = function () use ($level2) {
        throw $level2;
    };

    $middleware->handle($job, $next);

    $job->shouldHaveReceived('release')->once()->with(15);
});
