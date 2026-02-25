<?php

namespace MobileStock\LaravelResilience\Queue\Middleware;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Http\Client\RequestException;
use MobileStock\LaravelResilience\Queue\Middleware\Concerns\CalculatesBackoff;
use Symfony\Component\HttpFoundation\Response;

class HttpClientRetryMiddleware
{
    use CalculatesBackoff;

    public function handle(object $job, callable $next): void
    {
        try {
            $next($job);
        } catch (RequestException $exception) {
            if (!$this->shouldRetry($exception)) {
                throw $exception;
            }

            $delay = $this->getRetryAfter($exception);
            if (!$delay) {
                $attempts = $job->attempts();
                $delay = $this->calculateBackoff($attempts);
            }

            $job->release($delay);
        }
    }

    protected function shouldRetry(RequestException $exception): bool
    {
        return $exception->response?->status() === Response::HTTP_TOO_MANY_REQUESTS;
    }

    protected function getRetryAfter(RequestException $exception): ?int
    {
        $retryAfter = $exception->response?->header('Retry-After');

        if (!$retryAfter) {
            return null;
        }

        if (is_numeric($retryAfter)) {
            return (int) $retryAfter;
        }

        return $this->parseRetryAfterDate($retryAfter);
    }

    protected function parseRetryAfterDate(string $retryAfter): ?int
    {
        $date = DateTimeImmutable::createFromFormat(DateTimeInterface::RFC1123, $retryAfter);

        if (!$date) {
            return null;
        }

        return max(0, $date->getTimestamp() - (new DateTimeImmutable())->getTimestamp());
    }
}
