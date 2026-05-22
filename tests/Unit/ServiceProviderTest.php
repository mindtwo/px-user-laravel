<?php

use Laravel\Scout\EngineManager;
use mindtwo\PxUserLaravel\Http\Client\PxUserAdminClient;
use mindtwo\PxUserLaravel\Http\Client\PxUserClient;
use mindtwo\PxUserLaravel\Scout\PxUserEngine;
use mindtwo\PxUserLaravel\Services\PxUserCachedApiService;
use mindtwo\PxUserLaravel\Services\PxUserTokens;

test('PxUserClient is registered in service container', function () {
    $client = app(PxUserClient::class);

    expect($client)->toBeInstanceOf(PxUserClient::class);
});

test('PxUserAdminClient is registered in service container', function () {
    $client = app(PxUserAdminClient::class);

    expect($client)->toBeInstanceOf(PxUserAdminClient::class);
});

test('PxUserCachedApiService is registered in service container', function () {
    $service = app(PxUserCachedApiService::class);

    expect($service)->toBeInstanceOf(PxUserCachedApiService::class);
});

test('PxUserTokens service is resolvable from container', function () {
    $service = app(PxUserTokens::class);

    expect($service)->toBeInstanceOf(PxUserTokens::class);
});

test('scout engine is registered', function () {
    $manager = app(EngineManager::class);

    expect($manager->engine('px-user'))->toBeInstanceOf(PxUserEngine::class);
});

test('config is merged from package', function () {
    expect(config('px-user'))->not->toBeNull()
        ->and(config('px-user.px_user_id'))->toBe('px_user_id')
        ->and(config('px-user.px_user_cache_time'))->toBe(120);
});
