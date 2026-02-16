<?php

use Illuminate\Support\Facades\Http;
use mindtwo\PxUserLaravel\DataTransfer\PxUserData;
use mindtwo\PxUserLaravel\PxUser;
use mindtwo\PxUserLaravel\Tests\Fake\User;

beforeEach(function () {
    config(['px-user.user_model' => User::class]);
    config(['px-user.px_user_id' => 'px_user_id']);
    config(['px-user.domain' => 'test-domain']);
    config(['px-user.tenant' => 'test-tenant']);
});

test('validateToken returns true for valid token data', function () {
    $pxUser = new PxUser;

    $validTokenData = [
        'access_token' => 'valid-token',
        'access_token_expiration_utc' => '2026-12-31T23:59:59Z',
    ];

    expect($pxUser->validateToken($validTokenData))->toBeTrue();
});

test('validateToken returns false when access_token is missing', function () {
    $pxUser = new PxUser;

    $invalidTokenData = [
        'access_token_expiration_utc' => '2026-12-31T23:59:59Z',
    ];

    expect($pxUser->validateToken($invalidTokenData))->toBeFalse();
});

test('validateToken returns false when access_token_expiration_utc is missing', function () {
    $pxUser = new PxUser;

    $invalidTokenData = [
        'access_token' => 'valid-token',
    ];

    expect($pxUser->validateToken($invalidTokenData))->toBeFalse();
});

test('validateToken accepts optional refresh_token fields', function () {
    $pxUser = new PxUser;

    $validTokenData = [
        'access_token' => 'valid-token',
        'access_token_expiration_utc' => '2026-12-31T23:59:59Z',
        'refresh_token' => 'refresh-token',
        'refresh_token_expiration_utc' => '2026-12-31T23:59:59Z',
    ];

    expect($pxUser->validateToken($validTokenData))->toBeTrue();
});

test('find returns user by px_user_id', function () {
    $user = User::factory()->create(['px_user_id' => 'test-user-123']);

    $pxUser = new PxUser;
    $foundUser = $pxUser->find('test-user-123');

    expect($foundUser)->not->toBeNull()
        ->and($foundUser->px_user_id)->toBe('test-user-123');
});

test('find returns null when user not found', function () {
    $pxUser = new PxUser;
    $foundUser = $pxUser->find('non-existent-user');

    expect($foundUser)->toBeNull();
});

test('find returns null when no user model configured', function () {
    config(['px-user.user_model' => null]);

    $pxUser = new PxUser;
    $foundUser = $pxUser->find('test-user-123');

    expect($foundUser)->toBeNull();
});

test('retrieve creates new user when not exists', function () {
    Http::fake([
        '*/v1/user' => Http::response([
            'user' => [
                'id' => 'new-user-123',
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
            ],
        ]),
    ]);

    $pxUser = new PxUser;

    // Create mock PxUserData
    $userData = PxUserData::from([
        'id' => 'new-user-123',
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
    ]);

    $user = $pxUser->retrieve($userData);

    expect($user)->not->toBeFalse()
        ->and($user->px_user_id)->toBe('new-user-123')
        ->and($user->wasRecentlyCreated)->toBeTrue();
});

test('retrieve returns existing user when already exists', function () {
    $existingUser = User::factory()->create(['px_user_id' => 'existing-user-123']);

    $pxUser = new PxUser;

    // Create mock PxUserData
    $userData = PxUserData::from([
        'id' => 'existing-user-123',
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
    ]);

    $user = $pxUser->retrieve($userData);

    expect($user)->not->toBeFalse()
        ->and($user->id)->toBe($existingUser->id)
        ->and($user->wasRecentlyCreated)->toBeFalse();
});

test('retrieve returns false when no user model configured', function () {
    config(['px-user.user_model' => null]);

    $pxUser = new PxUser;

    $userData = PxUserData::from([
        'id' => 'test-user-123',
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
    ]);

    $user = $pxUser->retrieve($userData);

    expect($user)->toBeFalse();
});
