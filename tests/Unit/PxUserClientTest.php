<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use mindtwo\PxUserLaravel\DataTransfer\PxUserData;
use mindtwo\PxUserLaravel\Http\Client\PxUserClient;
use mindtwo\PxUserLaravel\Tests\Fake\User;

beforeEach(function () {
    config(['px-user.user_model' => User::class]);
    config(['px-user.px_user_id' => 'px_user_id']);
    config(['px-user.domain' => 'test-domain']);
    config(['px-user.tenant' => 'test-tenant']);
    config(['px-user.apiClient.baseUrl' => 'https://api.example.com']);
});

test('setDomainCode sets domain code override', function () {
    $client = app(PxUserClient::class);

    $result = $client->setDomainCode('custom-domain');

    expect($result)->toBe($client);
});

test('setTenantCode sets tenant code override', function () {
    $client = app(PxUserClient::class);

    $result = $client->setTenantCode('custom-tenant');

    expect($result)->toBe($client);
});

test('setAccessToken sets access token override', function () {
    $client = app(PxUserClient::class);

    $result = $client->setAccessToken('custom-token');

    expect($result)->toBe($client);
});

test('methods can be chained', function () {
    $client = app(PxUserClient::class);

    $result = $client
        ->setAccessToken('token')
        ->setDomainCode('domain')
        ->setTenantCode('tenant');

    expect($result)->toBe($client);
});

test('apiName returns px-user', function () {
    $client = app(PxUserClient::class);

    expect($client->apiName())->toBe('px-user');
});

test('getUsersDetails returns single user when string provided', function () {
    Http::fake([
        '*/v1/users/details' => Http::response([
            'response' => [
                [
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
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create(['px_user_id' => 'test-user-123']);
    Auth::login($user);

    $client = app(PxUserClient::class);
    $client->setAccessToken('test-token');

    $result = $client->getUsersDetails('user-123');

    expect($result)->toBeInstanceOf(PxUserData::class)
        ->and($result->id)->toBe('user-123');
});

test('getUsersDetails returns array when array provided', function () {
    Http::fake([
        '*/v1/users/details' => Http::response([
            'response' => [
                [
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
                ],
                [
                    'id' => 'user-456',
                    'email' => 'test2@example.com',
                    'preferred_username' => 'testuser2',
                    'tenant_code' => 'test-tenant',
                    'domain_code' => 'test-domain',
                    'is_enabled' => true,
                    'is_confirmed' => true,
                    'firstname' => 'Test2',
                    'lastname' => 'User2',
                    'activated_at' => null,
                    'last_login_at' => null,
                    'roles' => [],
                    'products' => [],
                    'source' => 'test',
                    'locale' => 'en',
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create(['px_user_id' => 'test-user-123']);
    Auth::login($user);

    $client = app(PxUserClient::class);
    $client->setAccessToken('test-token');

    $result = $client->getUsersDetails(['user-123', 'user-456']);

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(2)
        ->and($result[0]->id)->toBe('user-123')
        ->and($result[1]->id)->toBe('user-456');
});
