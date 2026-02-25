<?php

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use MobileStock\LaravelResilience\Queue\Middleware\HttpClientRetryMiddleware;

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
