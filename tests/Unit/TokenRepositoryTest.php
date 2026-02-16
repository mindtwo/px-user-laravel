<?php

use mindtwo\PxUserLaravel\ExternalApiTokens\PxUserEloquentTokenRepository;
use mindtwo\PxUserLaravel\ExternalApiTokens\PxUserRedisTokenRepository;
use mindtwo\TwoTility\ExternalApiTokens\Contracts\ExternalApiTokenRepository;

test('PxUserRedisTokenRepository has correct api name', function () {
    $repo = new PxUserRedisTokenRepository;

    $reflection = new \ReflectionClass($repo);
    $property = $reflection->getProperty('apiName');
    $property->setAccessible(true);

    expect($property->getValue($repo))->toBe('px-user');
});

test('PxUserRedisTokenRepository has correct key mapping', function () {
    $repo = new PxUserRedisTokenRepository;

    $reflection = new \ReflectionClass($repo);
    $property = $reflection->getProperty('keyMapping');
    $property->setAccessible(true);

    $keyMapping = $property->getValue($repo);

    expect($keyMapping)->toHaveKey('access_token')
        ->and($keyMapping)->toHaveKey('refresh_token')
        ->and($keyMapping)->toHaveKey('expires_at')
        ->and($keyMapping)->toHaveKey('refresh_token_valid_until');
});

test('PxUserEloquentTokenRepository has correct api name', function () {
    $repo = new PxUserEloquentTokenRepository;

    $reflection = new \ReflectionClass($repo);
    $property = $reflection->getProperty('apiName');
    $property->setAccessible(true);

    expect($property->getValue($repo))->toBe('px-user');
});

test('PxUserEloquentTokenRepository has correct key mapping', function () {
    $repo = new PxUserEloquentTokenRepository;

    $reflection = new \ReflectionClass($repo);
    $property = $reflection->getProperty('keyMapping');
    $property->setAccessible(true);

    $keyMapping = $property->getValue($repo);

    expect($keyMapping)->toHaveKey('access_token')
        ->and($keyMapping)->toHaveKey('refresh_token')
        ->and($keyMapping)->toHaveKey('expires_at')
        ->and($keyMapping)->toHaveKey('refresh_token_valid_until');
});

test('both repositories implement ExternalApiTokenRepository contract', function () {
    $redisRepo = new PxUserRedisTokenRepository;
    $eloquentRepo = new PxUserEloquentTokenRepository;

    expect($redisRepo)->toBeInstanceOf(ExternalApiTokenRepository::class)
        ->and($eloquentRepo)->toBeInstanceOf(ExternalApiTokenRepository::class);
});
