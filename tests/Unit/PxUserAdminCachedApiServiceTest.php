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

test('looks a PxUser up in their own tenant and domain', function () {
    Http::fake(['*/user/user-1' => Http::response(adminUserResponse('user-1'))]);

    $user = User::factory()->make(['px_user_id' => 'user-1']);
    $user->px_user_tenant_code = 'other-tenant';
    $user->px_user_domain_code = 'other-domain';

    $userData = app(PxUserAdminCachedApiService::class)->getUser($user);

    expect($userData)->toBeInstanceOf(PxUserData::class)
        ->and($userData->email)->toBe('jane@example.com');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://user.api.example.com/user/user-1'
        && $request->hasHeader('x-m2m-authorization', 'm2m-secret')
        && $request->hasHeader('x-context-tenant-code', 'other-tenant')
        && $request->hasHeader('x-context-domain-code', 'other-domain'));
});

test('looks a plain user id up without context', function () {
    Http::fake(['*/user/user-1' => Http::response(adminUserResponse('user-1'))]);

    app(PxUserAdminCachedApiService::class)->getUser('user-1');

    Http::assertSent(fn (Request $request): bool => ! $request->hasHeader('x-context-tenant-code')
        && ! $request->hasHeader('x-context-domain-code'));
});

test('caches the user data under the px-user cache key', function () {
    Http::fake(['*/user/user-1' => Http::response(adminUserResponse('user-1'))]);

    $user = User::factory()->make(['px_user_id' => 'user-1']);
    $service = app(PxUserAdminCachedApiService::class);

    $service->getUser($user);
    $service->getUser($user);

    Http::assertSentCount(1);
    expect(Cache::get($user->cachedAttributeKey()))->toBeInstanceOf(PxUserData::class);
});

test('resolves a user unknown to PX to null and remembers the miss', function () {
    Http::fake(['*/user/user-1' => Http::response(['success' => false, 'code' => 404], 404)]);

    $user = User::factory()->make(['px_user_id' => 'user-1']);
    $service = app(PxUserAdminCachedApiService::class);

    expect($service->getUser($user))->toBeNull()
        ->and($service->getUser($user))->toBeNull();

    Http::assertSentCount(1);
});

test('looks a missing user up again once the miss expired', function () {
    config(['px-user.px_user_miss_cache_time' => 5]);
    Http::fake(['*/user/user-1' => Http::sequence()
        ->push(['success' => false, 'code' => 404], 404)
        ->push(adminUserResponse('user-1')),
    ]);

    $service = app(PxUserAdminCachedApiService::class);

    expect($service->getUser('user-1'))->toBeNull();

    $this->travel(6)->minutes();

    expect($service->getUser('user-1'))->toBeInstanceOf(PxUserData::class);
    Http::assertSentCount(2);
});

test('rethrows PX errors other than 404 without remembering a miss', function () {
    Http::fake(['*/user/user-1' => Http::sequence()
        ->push(['success' => false], 500)
        ->push(adminUserResponse('user-1')),
    ]);

    $service = app(PxUserAdminCachedApiService::class);

    expect(fn () => $service->getUser('user-1'))->toThrow(RequestException::class)
        ->and($service->getUser('user-1'))->toBeInstanceOf(PxUserData::class);
});
