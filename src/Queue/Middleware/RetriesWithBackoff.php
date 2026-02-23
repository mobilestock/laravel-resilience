<?php

namespace MobileStock\LaravelResilience\Queue\Middleware;

use Illuminate\Http\Client\RequestException;
use MobileStock\LaravelResilience\Contracts\RetryableException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RetriesWithBackoff
{
    private const int BACKOFF_BASE = 2;
    private const float JITTER_PERCENTAGE = 0.5;

    /**
     * @param mixed $job
     * @param callable $next
     */
    public function handle($job, $next): void
    {
        $jobProxy = new class ($job) {
            public function __construct(private readonly mixed $job)
            {
            }

            public function release(int $delay = 0): void
            {
                throw new class ($this->job->attempts()) extends \Exception implements RetryableException {
                    public function __construct(public readonly int $attempts)
                    {
                        parent::__construct('Job released by user');
                    }
                };
            }

            public function __get(string $name): mixed
            {
                return $this->job->$name;
            }

            public function __call(string $name, array $arguments): mixed
            {
                return $this->job->$name(...$arguments);
            }
        };

        try {
            $next($jobProxy);
        } catch (Throwable $e) {
            if ($this->shouldRetry($e)) {
                $job->release($this->calculateBackoff($job->attempts()));

                return;
            }

            $job->fail($e);
        }
    }

    protected function shouldRetry(Throwable $e): bool
    {
        return $e instanceof RetryableException || $this->isRateLimited($e);
    }

    protected function isRateLimited(Throwable $e): bool
    {
        return $e instanceof RequestException && $e->response?->status() === Response::HTTP_TOO_MANY_REQUESTS;
    }

    protected function calculateBackoff(int $attempts): int
    {
        $delay = self::BACKOFF_BASE ** $attempts;
        $jitter = random_int(0, (int) ($delay * self::JITTER_PERCENTAGE * 1000)) / 1000;

        return (int) ($delay + $jitter);
    }
}
