<?php

use mindtwo\PxUserLaravel\DataTransfer\PxUserDataWithPermissions;

/**
 * A user payload as `v1/user-with-permissions` returns it.
 */
function pxUserDataWithPermissionsPayload(array $overrides = []): array
{
    return array_merge([
        'id' => 'user-123',
        'correlated_id' => 'correlated-123',
        'created_at' => '2024-02-19T08:08:25+00:00',
        'external_ids' => [],
        'tenant_code' => 'test-tenant',
        'domain_code' => 'test-domain',
        'email' => 'test@example.com',
        'preferred_username' => 'test@example.com',
        'is_enabled' => true,
        'is_confirmed' => true,
        'suspended' => false,
        'is_human' => true,
        'firstname' => 'Test',
        'lastname' => 'User',
        'gender' => '',
        'last_login_at' => '2026-09-07T14:33:39+00:00',
        'last_activity_at' => '2026-09-07T14:33:39+00:00',
        'activated_at' => null,
        'source' => 'api.test',
        'locale' => 'de',
        'mfa_enabled' => false,
        'mfa_mandatory' => false,
        'products' => ['test-product'],
        'capabilities' => [
            'tenants' => [
                'test-tenant' => [
                    'domains' => [
                        'test-domain' => [
                            'products' => [
                                'test-product' => ['roles' => ['standard']],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ], $overrides);
}

test('blank timestamps hydrate as null', function () {
    $data = PxUserDataWithPermissions::from(pxUserDataWithPermissionsPayload([
        'last_login_at' => '',
        'last_activity_at' => '',
    ]));

    expect($data->lastLoginAt)->toBeNull()
        ->and($data->lastActivityAt)->toBeNull();
});

test('supplied timestamps are still hydrated', function () {
    $data = PxUserDataWithPermissions::from(pxUserDataWithPermissionsPayload());

    expect($data->lastLoginAt->toIso8601String())->toBe('2026-09-07T14:33:39+00:00')
        ->and($data->lastActivityAt->toIso8601String())->toBe('2026-09-07T14:33:39+00:00');
});

test('a user that never logged in resolves its roles from capabilities', function () {
    $data = PxUserDataWithPermissions::from(pxUserDataWithPermissionsPayload([
        'last_login_at' => '',
    ]));

    expect($data->lastLoginAt)->toBeNull()
        ->and($data->roles)->toBe(['test-product' => ['standard']]);
});
