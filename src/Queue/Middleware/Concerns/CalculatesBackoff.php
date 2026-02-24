<?php

namespace MobileStock\LaravelResilience\Queue\Middleware\Concerns;

trait CalculatesBackoff
{
    public function calculateBackoff(int $attempts): int
    {
        $delay = 2 ** $attempts;
        $jitter = random_int(0, (int) ($delay * 0.5 * 1000)) / 1000;

        return (int) ($delay + $jitter);
    }
}
