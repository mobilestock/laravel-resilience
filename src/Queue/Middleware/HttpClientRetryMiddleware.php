<?php

namespace MobileStock\LaravelResilience\Queue\Middleware;

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
            if ($this->shouldRetry($exception)) {
                $delay = $this->getRetryAfter($exception) ?? $this->calculateBackoff($job->attempts());
                $job->release($delay);

                return;
            }

            throw $exception;
        }
    }

    protected function shouldRetry(RequestException $exception): bool
    {
        return $exception->response?->status() === Response::HTTP_TOO_MANY_REQUESTS;
    }

    protected function getRetryAfter(RequestException $exception): ?int
    {
        $retryAfter = $exception->response?->header('Retry-After');

        if (is_numeric($retryAfter)) {
            return (int) $retryAfter;
        }

        if ($retryAfter) {
            $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC1123, $retryAfter);
            if ($date) {
                return max(0, $date->getTimestamp() - time());
            }
        }

        return null;
    }
}
