<?php

namespace Tests;

use MobileStock\LaravelResilience\Providers\ResilienceServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [ResilienceServiceProvider::class];
    }
}
