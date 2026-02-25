<?php

test('service provider is registered', function () {
    expect(
        app()->getProvider(MobileStock\LaravelResilience\Providers\ResilienceServiceProvider::class)
    )->not->toBeNull();
});

test('config is merged', function () {
    expect(config('resilience.middlewares'))->toBeArray();
});
