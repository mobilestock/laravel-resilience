<?php

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use MobileStock\LaravelResilience\Queue\Middleware\HttpClientRetryMiddleware;
use Psr\Http\Message\RequestInterface;

beforeEach(function () {
    /** @var Tests\TestCase $this */
    $this->middleware = new HttpClientRetryMiddleware();
    $this->job = Mockery::spy();
});

it('should release the job when a retry is possible', function (int $attempts, array $headers, callable $assertion) {
    /** @var Tests\TestCase $this */
    $middleware = $this->middleware;
    $job = $this->job;

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
    'with float numeric header' => [
        1,
        ['Retry-After' => '60.5'],
        fn($job) => $job->shouldHaveReceived('release')->once()->with(60),
    ],
    'with very large number' => [
        1,
        ['Retry-After' => '999999999'],
        fn($job) => $job->shouldHaveReceived('release')->once()->with(999999999),
    ],
]);

it('should release job when RequestException is wrapped or nested', function (
    int $attempts,
    ?string $retryAfter = null,
    int|array|null $expectedDelay = null
) {
    /** @var Tests\TestCase $this */
    $middleware = $this->middleware;
    $job = $this->job;

    $job->allows('attempts')->andReturn($attempts);
    $headers = $retryAfter ? ['Retry-After' => $retryAfter] : [];
    $response = Http::fake([
        '*' => Http::response([], 429, $headers),
    ])->get('http://dummy.com');
    $requestException = new RequestException($response);

    $next = function () use ($requestException) {
        throw $requestException;
    };

    $middleware->handle($job, $next);

    if (is_array($expectedDelay)) {
        $job->shouldHaveReceived('release')
            ->once()
            ->with(Mockery::on(fn($delay) => $delay >= $expectedDelay[0] && $delay <= $expectedDelay[1]));
    } else {
        $job->shouldHaveReceived('release')->once()->with($expectedDelay);
    }
})->with([
    'RequestException direct with retry-after 30' => [1, '30', 30],
    'RequestException direct with no retry-after' => [3, null, [8, 12]],
]);

it('should release job when wrapped RequestException', function () {
    /** @var Tests\TestCase $this */
    $middleware = $this->middleware;
    $job = $this->job;

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

it('should release job with nested RequestException at multiple levels', function (
    int $nesting,
    ?string $retryAfter = null
) {
    /** @var Tests\TestCase $this */
    $middleware = $this->middleware;
    $job = $this->job;

    $job->allows('attempts')->andReturn(1);
    $headers = $retryAfter ? ['Retry-After' => $retryAfter] : [];
    $response = Http::fake([
        '*' => Http::response([], 429, $headers),
    ])->get('http://dummy.com');
    $requestException = new RequestException($response);

    $exception = $requestException;
    for ($i = 0; $i < $nesting; $i++) {
        $exception = new RuntimeException('Level ' . ($i + 1), 0, $exception);
    }

    $next = function () use ($exception) {
        throw $exception;
    };

    $middleware->handle($job, $next);

    $job->shouldHaveReceived('release')
        ->once()
        ->with($retryAfter ? (int) $retryAfter : Mockery::on(fn($delay) => $delay >= 2 && $delay <= 6));
})->with([
    '2 levels deep' => [1, null],
    '5 levels deep' => [4, '25'],
]);

it('should release job when ClientException is thrown directly or wrapped', function (
    callable $exceptionFactory,
    int $attempts,
    int|array|null $expectedDelay = null
) {
    /** @var Tests\TestCase $this */
    $middleware = $this->middleware;
    $job = $this->job;

    $job->allows('attempts')->andReturn($attempts);
    $exception = $exceptionFactory();
    $next = function () use ($exception) {
        throw $exception;
    };

    $middleware->handle($job, $next);

    if (is_array($expectedDelay)) {
        $job->shouldHaveReceived('release')
            ->once()
            ->with(Mockery::on(fn($delay) => $delay >= $expectedDelay[0] && $delay <= $expectedDelay[1]));
    } else {
        $job->shouldHaveReceived('release')->once()->with($expectedDelay);
    }
})->with([
    'ClientException direct with retry-after 45' => [
        fn() => new ClientException(
            'Too Many Requests',
            Mockery::mock(RequestInterface::class),
            new GuzzleResponse(429, ['Retry-After' => '45'])
        ),
        1,
        45,
    ],
    'ClientException wrapped with backoff' => [
        fn() => new RuntimeException(
            'Wrapped',
            0,
            new ClientException('Too Many Requests', Mockery::mock(RequestInterface::class), new GuzzleResponse(429))
        ),
        2,
        [4, 6],
    ],
    'ClientException with mocked response and numeric header' => [
        fn() => (function () {
            $mock = Mockery::mock(GuzzleResponse::class);
            $mock->shouldReceive('getStatusCode')->andReturn(429);
            $mock->shouldReceive('getHeaderLine')->with('Retry-After')->andReturn('50');
            return new ClientException('Too Many Requests', Mockery::mock(RequestInterface::class), $mock);
        })(),
        1,
        50,
    ],
    'ClientException with mocked response and empty header' => [
        fn() => (function () {
            $mock = Mockery::mock(GuzzleResponse::class);
            $mock->shouldReceive('getStatusCode')->andReturn(429);
            $mock->shouldReceive('getHeaderLine')->with('Retry-After')->andReturn('');
            return new ClientException('Too Many Requests', Mockery::mock(RequestInterface::class), $mock);
        })(),
        1,
        [2, 4],
    ],
]);

it('should re-throw exceptions that do not represent retryable errors', function (
    callable $exceptionFactory,
    ?string $expectedClass = null
) {
    /** @var Tests\TestCase $this */
    $middleware = $this->middleware;
    $job = $this->job;

    $exception = $exceptionFactory();
    $next = function () use ($exception) {
        throw $exception;
    };

    expect(fn() => $middleware->handle($job, $next))->toThrow($expectedClass ?? Exception::class);
})->with([
    'RuntimeException without HTTP context' => [
        fn() => new RuntimeException('Not an HTTP exception'),
        RuntimeException::class,
    ],
    'RequestException wrapped but not 429' => [
        fn() => new RuntimeException(
            'Wrapped',
            0,
            new RequestException(Http::fake(['*' => Http::response([], 500)])->get('http://dummy.com'))
        ),
        RuntimeException::class,
    ],
    'ClientException with non-429 status' => [
        fn() => new ClientException(
            'Internal Server Error',
            Mockery::mock(RequestInterface::class),
            new GuzzleResponse(500, ['Retry-After' => '30'])
        ),
        ClientException::class,
    ],
    'Exception with getResponse returning non-ResponseInterface object' => [
        fn() => new class (new stdClass()) extends Exception {
            private $responseObject;

            public function __construct($obj)
            {
                parent::__construct();
                $this->responseObject = $obj;
            }

            public function getResponse()
            {
                return $this->responseObject;
            }
        },
        Exception::class,
    ],
    'Exception with getResponse returning null' => [
        fn() => new class extends Exception {
            public function getResponse()
            {
                return null;
            }
        },
        Exception::class,
    ],
    'Exception without getResponse method' => [
        fn() => new RuntimeException('Standard exception without getResponse'),
        RuntimeException::class,
    ],
    'RequestException with null response' => [
        fn() => (function () {
            $reflection = new ReflectionClass(RequestException::class);
            $exception = $reflection->newInstanceWithoutConstructor();
            $exception->response = null;
            return $exception;
        })(),
        RequestException::class,
    ],
]);

it('should not interact with job when no exception is thrown', function () {
    /** @var Tests\TestCase $this */
    $middleware = $this->middleware;
    $job = $this->job;

    $next = function () {
        return 'success';
    };

    $middleware->handle($job, $next);

    $job->shouldNotHaveReceived('release');
});

it('should release job with backoff when response becomes unavailable during processing', function () {
    /** @var Tests\TestCase $this */
    $middleware = $this->middleware;
    $job = $this->job;

    $job->allows('attempts')->andReturn(2);
    $response = new GuzzleResponse(429);
    $exception = new class ($response) extends Exception {
        private int $calls = 0;

        public function __construct(private readonly GuzzleResponse $response)
        {
            parent::__construct('Transient HTTP exception');
        }

        public function getResponse(): ?GuzzleResponse
        {
            $this->calls++;

            return $this->calls < 3 ? $this->response : null;
        }
    };
    $next = function () use ($exception) {
        throw $exception;
    };

    $middleware->handle($job, $next);

    $job->shouldHaveReceived('release')
        ->once()
        ->with(Mockery::on(fn($delay) => $delay >= 4 && $delay <= 6));
});
