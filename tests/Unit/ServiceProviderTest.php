<?php

use Laravel\Scout\EngineManager;
use mindtwo\PxUserLaravel\ExternalApiTokens\PxUserEloquentTokenRepository;
use mindtwo\PxUserLaravel\ExternalApiTokens\PxUserRedisTokenRepository;
use mindtwo\PxUserLaravel\Http\Client\PxUserAdminClient;
use mindtwo\PxUserLaravel\Http\Client\PxUserClient;
use mindtwo\PxUserLaravel\PxUserProvider;
use mindtwo\PxUserLaravel\Scout\PxUserEngine;
use mindtwo\PxUserLaravel\Services\PxUserCachedApiService;

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

test('external-api token repository is configured for px-user', function () {
    expect(config('external-api.apis.px-user'))->not->toBeNull()
        ->and(config('external-api.apis.px-user.repository'))->toBe('px-user');
});

test('token repository uses redis when driver is redis', function () {
    config(['px-user.token_driver' => 'redis']);

    // Re-bootstrap the provider to apply config change
    $this->app->register(PxUserProvider::class, true);

    $repositoryClass = config('external-api.alias.px-user');

    expect($repositoryClass)->toBe(PxUserRedisTokenRepository::class);
});

test('token repository uses eloquent when driver is eloquent', function () {
    config(['px-user.token_driver' => 'eloquent']);

    // Re-bootstrap the provider to apply config change
    $this->app->register(PxUserProvider::class, true);

    $repositoryClass = config('external-api.alias.px-user');

    expect($repositoryClass)->toBe(PxUserEloquentTokenRepository::class);
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
