<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Laravel Resilience Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can configure the default settings for your resilience strategies.
    |
    */
    'middlewares' => [
        MobileStock\LaravelResilience\Queue\Middleware\FailJobOnExceptionMiddleware::class,
        MobileStock\LaravelResilience\Queue\Middleware\RetryableExceptionMiddleware::class,
        MobileStock\LaravelResilience\Queue\Middleware\HttpClientRetryMiddleware::class,
        MobileStock\LaravelResilience\Queue\Middleware\RetryDeadlockMiddleware::class,
    ],
];
