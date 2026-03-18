<?php

namespace Tests;

use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use MobileStock\LaravelResilience\Providers\ResilienceServiceProvider;
use MobileStock\LaravelResilience\Queue\Middleware\HttpClientRetryMiddleware;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public HttpClientRetryMiddleware $middleware;

    public LegacyMockInterface|MockInterface $job;

    protected function getPackageProviders($app): array
    {
        return [ResilienceServiceProvider::class];
    }
}
