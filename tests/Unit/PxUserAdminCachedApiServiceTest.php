<?php

use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use mindtwo\PxUserLaravel\DataTransfer\PxUserData;
use mindtwo\PxUserLaravel\Services\PxUserAdminCachedApiService;
use mindtwo\PxUserLaravel\Tests\Fake\User;

beforeEach(function () {
    config([
        'px-user.user_model' => User::class,
        'px-user.px_user_id' => 'px_user_id',
        'px-user.tenant' => 'default-tenant',
        'px-user.domain' => 'default-domain',
        'px-user.m2m_credentials' => 'm2m-secret',
        'px-user.apiClient.baseUrl' => 'https://user.api.example.com',
    ]);

    Cache::flush();
});

function adminUserResponse(string $userId): array
{
    return [
        'response' => [
            'user' => [
                'id' => $userId,
                'email' => 'jane@example.com',
                'preferred_username' => 'jane',
                'tenant_code' => 'other-tenant',
                'domain_code' => 'other-domain',
                'firstname' => 'Jane',
                'lastname' => 'Doe',
                'activated_at' => null,
                'last_login_at' => null,
                'roles' => [],
                'products' => [],
                'locale' => 'de',
            ],
        ],
    ];
}

test('looks the user up in the given tenant and domain', function () {
    Http::fake(['*/user/user-1' => Http::response(adminUserResponse('user-1'))]);

    $userData = app(PxUserAdminCachedApiService::class)->getUser('user-1', 'other-tenant', 'other-domain');

    expect($userData)->toBeInstanceOf(PxUserData::class)
        ->and($userData->email)->toBe('jane@example.com');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://user.api.example.com/user/user-1'
        && $request->hasHeader('x-m2m-authorization', 'm2m-secret')
        && $request->hasHeader('x-context-tenant-code', 'other-tenant')
        && $request->hasHeader('x-context-domain-code', 'other-domain'));
});

test('caches the user data under the px-user cache key', function () {
    Http::fake(['*/user/user-1' => Http::response(adminUserResponse('user-1'))]);

    $user = User::factory()->make(['px_user_id' => 'user-1']);
    $service = app(PxUserAdminCachedApiService::class);

    $service->getUser('user-1', 'other-tenant', 'other-domain');
    $service->getUser('user-1', 'other-tenant', 'other-domain');

    Http::assertSentCount(1);
    expect(Cache::get($user->cachedAttributeKey()))->toBeInstanceOf(PxUserData::class);
});

test('throws PX errors without caching them', function () {
    Http::fake(['*/user/user-1' => Http::sequence()
        ->push(['success' => false, 'code' => 404], 404)
        ->push(adminUserResponse('user-1')),
    ]);

    $service = app(PxUserAdminCachedApiService::class);

    expect(fn () => $service->getUser('user-1', 'other-tenant', 'other-domain'))->toThrow(RequestException::class)
        ->and($service->getUser('user-1', 'other-tenant', 'other-domain'))->toBeInstanceOf(PxUserData::class);
});

test('HasPxUser looks the user up in their own tenant and domain in console context', function () {
    Http::fake(['*/user/user-1' => Http::response(adminUserResponse('user-1'))]);

    $user = User::factory()->make(['px_user_id' => 'user-1']);
    $user->px_user_tenant_code = 'other-tenant';
    $user->px_user_domain_code = 'other-domain';

    // Outside the test environment the console lookup runs through the admin service;
    // restored right after, since the migration rollback on teardown asks for confirmation in production
    app()->detectEnvironment(fn (): string => 'production');

    try {
        expect($user->email)->toBe('jane@example.com');
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-context-tenant-code', 'other-tenant')
        && $request->hasHeader('x-context-domain-code', 'other-domain'));
});
