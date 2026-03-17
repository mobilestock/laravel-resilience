<?php

namespace MobileStock\LaravelResilience\Queue\Middleware;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Response;
use MobileStock\LaravelResilience\Queue\Middleware\Concerns\CalculatesBackoff;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class HttpClientRetryMiddleware
{
    use CalculatesBackoff;

    public function handle(object $job, callable $next): void
    {
        try {
            $next($job);
        } catch (Throwable $exception) {
            $httpException = $this->findHttpException($exception);

            if (!$httpException || !$this->shouldRetry($httpException)) {
                throw $exception;
            }

            $delay = $this->getRetryAfter($httpException);
            if (!$delay) {
                $attempts = $job->attempts();
                $delay = $this->calculateBackoff($attempts);
            }

            $job->release($delay);
        }
    }

    protected function findHttpException(Throwable $exception): ?Throwable
    {
        $current = $exception;

        while ($current !== null) {
            if ($current instanceof RequestException || $this->hasResponse($current)) {
                return $current;
            }

            $current = $current->getPrevious();
        }

        return null;
    }

    protected function shouldRetry(Throwable $exception): bool
    {
        return $this->getStatusCode($exception) === Response::HTTP_TOO_MANY_REQUESTS;
    }

    protected function getStatusCode(Throwable $exception): ?int
    {
        if ($exception instanceof RequestException) {
            return $exception->response?->status();
        }

        return $this->getResponse($exception)?->getStatusCode();
    }

    protected function getRetryAfter(Throwable $exception): ?int
    {
        $retryAfter = $this->getRetryAfterHeader($exception);

        if (!$retryAfter) {
            return null;
        }

        $delay = is_numeric($retryAfter) ? (int) $retryAfter : $this->parseRetryAfterDate($retryAfter);

        return $delay > 0 ? $delay : null;
    }

    protected function getRetryAfterHeader(Throwable $exception): ?string
    {
        if ($exception instanceof RequestException) {
            return $exception->response?->header('Retry-After');
        }

        $response = $this->getResponse($exception);

        if (!$response) {
            return null;
        }

        $header = $response->getHeaderLine('Retry-After');

        return $header !== '' ? $header : null;
    }

    protected function hasResponse(Throwable $exception): bool
    {
        return $this->getResponse($exception) !== null;
    }

    protected function getResponse(Throwable $exception): ?ResponseInterface
    {
        if (!is_callable([$exception, 'getResponse'])) {
            return null;
        }

        $response = call_user_func([$exception, 'getResponse']);

        return $response instanceof ResponseInterface ? $response : null;
    }

    protected function parseRetryAfterDate(string $retryAfter): ?int
    {
        $date = DateTimeImmutable::createFromFormat(DateTimeInterface::RFC1123, $retryAfter);

        if (!$date) {
            return null;
        }

        $delay = $date->getTimestamp() - (new DateTimeImmutable())->getTimestamp();

        return $delay;
    }
}
