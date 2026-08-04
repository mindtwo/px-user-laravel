<?php

use mindtwo\PxUserLaravel\DataTransfer\PxUserData;
use Spatie\LaravelData\Optional;

function pxUserDataPayload(array $overrides = []): array
{
    return array_merge([
        'id' => 'user-123',
        'email' => 'test@example.com',
        'preferred_username' => 'testuser',
        'tenant_code' => 'test-tenant',
        'domain_code' => 'test-domain',
        'is_enabled' => true,
        'is_confirmed' => true,
        'firstname' => 'Test',
        'lastname' => 'User',
        'activated_at' => null,
        'last_login_at' => null,
        'roles' => [],
        'products' => [],
        'source' => 'test',
        'locale' => 'en',
    ], $overrides);
}

test('is_enabled, is_confirmed and source resolve to Optional when absent', function () {
    $payload = pxUserDataPayload();
    unset($payload['is_enabled'], $payload['is_confirmed'], $payload['source']);

    $data = PxUserData::from($payload);

    expect($data->isEnabled)->toBeInstanceOf(Optional::class)
        ->and($data->isConfirmed)->toBeInstanceOf(Optional::class)
        ->and($data->source)->toBeInstanceOf(Optional::class);
});

test('absent optional properties are omitted from the array representation', function () {
    $payload = pxUserDataPayload();
    unset($payload['is_enabled'], $payload['is_confirmed'], $payload['source']);

    $array = PxUserData::from($payload)->toArray();

    expect($array)->not->toHaveKeys(['isEnabled', 'isConfirmed', 'source'])
        ->and($array['id'])->toBe('user-123');
});

test('provided values are still hydrated and serialized', function () {
    $data = PxUserData::from(pxUserDataPayload([
        'is_enabled' => false,
        'is_confirmed' => false,
        'source' => 'oidc',
    ]));

    expect($data->isEnabled)->toBeFalse()
        ->and($data->isConfirmed)->toBeFalse()
        ->and($data->source)->toBe('oidc')
        ->and($data->toArray())->toMatchArray([
            'isEnabled' => false,
            'isConfirmed' => false,
            'source' => 'oidc',
        ]);
});
