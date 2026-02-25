<?php

namespace MobileStock\LaravelResilience\Queue\Middleware\Concerns;

trait CalculatesBackoff
{
    protected const BACKOFF_EXPONENTIAL_BASE = 2;
    protected const BACKOFF_MAX_DELAY_IN_SECONDS = 60 * 60 * 12; // 12 hours
    protected const BACKOFF_JITTER_FACTOR = 0.5;

    public function calculateBackoff(int $attempts): int
    {
        $delay = min(static::BACKOFF_EXPONENTIAL_BASE ** $attempts, static::BACKOFF_MAX_DELAY_IN_SECONDS);

        $jitter = random_int(0, (int) ($delay * static::BACKOFF_JITTER_FACTOR));

        return (int) ($delay + $jitter);
    }

    protected function releaseWithBackoff(object $job): void
    {
        $attempts = $job->attempts();
        $backoff = $this->calculateBackoff($attempts);

        $job->release($backoff);
    }
}
