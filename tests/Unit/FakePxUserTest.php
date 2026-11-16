<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use mindtwo\PxUserLaravel\Events\PxUserLoginEvent;
use mindtwo\PxUserLaravel\Events\PxUserTokenRefreshEvent;
use mindtwo\PxUserLaravel\Http\Client\PxUserAdminClient;
use mindtwo\PxUserLaravel\Http\Client\PxUserClient;
use mindtwo\PxUserLaravel\Http\Client\PxUserOidcClient;
use mindtwo\PxUserLaravel\Models\PxUserToken;
use mindtwo\PxUserLaravel\PxUser;
use mindtwo\PxUserLaravel\Services\PxUserCachedApiService;
use mindtwo\PxUserLaravel\Services\PxUserTokens;
use mindtwo\PxUserLaravel\Testing\Clients\FakePxUserClient;
use mindtwo\PxUserLaravel\Testing\FakePxUser;
use mindtwo\PxUserLaravel\Testing\PxUserData;
use mindtwo\PxUserLaravel\Testing\PxUserDataWithPermissions;
use mindtwo\PxUserLaravel\Testing\PxUserEndpoint;
use mindtwo\PxUserLaravel\Tests\Fake\User;

beforeEach(function () {
    config(['px-user.user_model' => User::class]);
    config(['px-user.px_user_id' => 'px_user_id']);
    config(['px-user.domain' => 'test-domain']);
    config(['px-user.tenant' => 'test-tenant']);
    config(['px-user.px_user_cache_time' => 120]);

    Cache::flush();
});

test('fake replaces the PxUser service in container', function () {
    FakePxUser::fake();

    expect(resolve(PxUser::class))->toBeInstanceOf(FakePxUser::class);
});

test('fake can login user', function () {
    $user = FakePxUser::actAs(User::factory()->create(), [
        'firstname' => 'Actor',
        'lastname' => 'As',
    ]);
    $this->actingAs($user);

    expect(auth()->user()->firstname)->toBe('Actor')
        ->and(auth()->user()->lastname)->toBe('As');

});

test('fakePxUserData seeds attributes for a non-authenticated user', function () {
    $actor = FakePxUser::actAs(User::factory()->create(['px_user_id' => 'actor-1']));
    $this->actingAs($actor);

    $recipient = User::factory()->create(['px_user_id' => 'recipient-1']);

    // Without seeding, the recipient's proxied attributes resolve to null.
    expect($recipient->email)->toBeNull();

    FakePxUser::fakePxUserData($recipient);

    $recipient = User::query()->find($recipient->getKey());

    expect($recipient->email)->toBe('recipient-1@example.com')
        ->and($recipient->firstname)->toBe('Test')
        ->and($recipient->lastname)->toBe('User')
        ->and($recipient->preferredUsername)->toBe('recipient-1');
});

test('fakePxUserData applies overrides', function () {
    $actor = FakePxUser::actAs(User::factory()->create(['px_user_id' => 'actor-1']));
    $this->actingAs($actor);

    $recipient = User::factory()->create(['px_user_id' => 'recipient-1']);

    FakePxUser::fakePxUserData($recipient, [
        'email' => 'custom@example.com',
        'firstname' => 'Custom',
    ]);

    $recipient = User::query()->find($recipient->getKey());

    expect($recipient->email)->toBe('custom@example.com')
        ->and($recipient->firstname)->toBe('Custom')
        ->and($recipient->lastname)->toBe('User');
});

test('fakePxUserData writes to the same cache key actAs uses', function () {
    $user = User::factory()->create(['px_user_id' => 'user-1']);

    FakePxUser::fakePxUserData($user, ['email' => 'seeded@example.com']);

    expect(Cache::get($user->cachedAttributeKey()))
        ->toMatchArray(['email' => 'seeded@example.com']);
});

test('a login with any token resolves to the user from actAs and fires the login event with their data', function () {
    Event::fake([PxUserLoginEvent::class]);
    $user = User::factory()->create(['px_user_id' => 'user-1']);
    FakePxUser::actAs($user, ['firstname' => 'Ada', 'roles' => ['lms' => ['admin']]]);

    $loggedIn = resolve(PxUser::class)->login(makeTokenData(['access_token' => 'token-from-the-spa']));

    expect($loggedIn->is($user))->toBeTrue()
        ->and(auth()->user()?->is($user))->toBeTrue();
    Event::assertDispatched(PxUserLoginEvent::class, fn (PxUserLoginEvent $event) => $event->userData->firstname === 'Ada'
        && $event->userData->roles === ['lms' => ['admin']]
        && $event->userData->tenantCode === 'test-tenant');
    FakePxUser::assertSent(PxUserEndpoint::UserWithPermissions, fn (array $call) => $call['access_token'] === 'token-from-the-spa');
});

test('a login without a known user creates a new one', function () {
    FakePxUser::fake();

    $user = resolve(PxUser::class)->login(makeTokenData());

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->wasRecentlyCreated)->toBeTrue();
});

test('a failing login endpoint throws the request exception', function () {
    FakePxUser::fail(PxUserEndpoint::UserWithPermissions, 401);

    expect(fn () => resolve(PxUser::class)->login(makeTokenData()))
        ->toThrow(fn (RequestException $e) => expect($e->response->status())->toBe(401));
});

test('keeps each user\'s data apart', function () {
    $first = User::factory()->create(['px_user_id' => 'first']);
    $second = User::factory()->create(['px_user_id' => 'second']);

    FakePxUser::actAs($first, ['email' => 'first@example.com']);
    FakePxUser::actAs($second, ['email' => 'second@example.com']);

    expect(User::query()->find($first->getKey())->email)->toBe('first@example.com')
        ->and(User::query()->find($second->getKey())->email)->toBe('second@example.com')
        ->and($first->fresh()->getPxUserAccessToken())->not->toBe($second->fresh()->getPxUserAccessToken());
});

test('loads the authenticated user\'s attributes through the real service without seeding them', function () {
    FakePxUser::fake();
    $user = User::factory()->create(['px_user_id' => 'plain']);
    $this->actingAs($user);

    expect($user->email)->toBe('plain@example.com');
    FakePxUser::assertSent(PxUserEndpoint::User, times: 1);
});

test('answers a user details batch with every user PX knows', function () {
    $viewer = User::factory()->create(['px_user_id' => 'viewer']);
    $this->actingAs($viewer);
    $listed = User::factory()->create(['px_user_id' => 'listed']);
    $forgotten = User::factory()->create(['px_user_id' => 'forgotten']);
    FakePxUser::forget($forgotten);

    $users = resolve(PxUserCachedApiService::class)->getUsersDetails(['listed', 'forgotten', 'not-a-user']);

    expect($users)->toHaveCount(1)
        ->and($users[0]->id)->toBe('listed');
    FakePxUser::assertSent(PxUserEndpoint::UsersDetails, fn (array $call) => $call['user_ids'] === ['listed', 'forgotten', 'not-a-user']
        && $call['tenant_code'] === 'test-tenant'
        && $call['product_code'] === 'lms');
});

test('serves seeded users from the cache without a details call', function () {
    $this->actingAs(User::factory()->create(['px_user_id' => 'viewer']));
    $seeded = User::factory()->create(['px_user_id' => 'seeded']);
    FakePxUser::user($seeded, ['firstname' => 'Seeded']);

    $users = resolve(PxUserCachedApiService::class)->getUsersDetails(['seeded']);

    expect($users[0]->firstname)->toBe('Seeded');
    FakePxUser::assertNotSent(PxUserEndpoint::UsersDetails);
});

test('refreshes an expired session', function () {
    $user = User::factory()->create(['px_user_id' => 'user-1']);
    $expired = FakePxUser::expireSession($user);

    expect(resolve(PxUser::class)->refreshSession($user))->toBeTrue()
        ->and($user->fresh()->getPxUserAccessToken())->not->toBe($expired['access_token']);
    FakePxUser::assertSent(PxUserEndpoint::RefreshTokens, fn (array $call) => $call['refresh_token'] === $expired['refresh_token']);
});

test('does not refresh a session whose refresh token has expired', function () {
    $user = User::factory()->create(['px_user_id' => 'user-1']);
    FakePxUser::expireSession($user, refreshable: false);

    expect(resolve(PxUser::class)->refreshSession($user))->toBeFalse();
    FakePxUser::assertNotSent(PxUserEndpoint::RefreshTokens);
});

test('fails a refresh PX rejects', function () {
    $user = User::factory()->create(['px_user_id' => 'user-1']);
    FakePxUser::expireSession($user);
    FakePxUser::fail(PxUserEndpoint::RefreshTokens, 401);

    expect(resolve(PxUser::class)->refreshSession($user))->toBeFalse();
});

test('logs out and invalidates the session, or throws when PX is unreachable', function () {
    $user = User::factory()->create(['px_user_id' => 'user-1']);
    FakePxUser::actAs($user);
    $this->actingAs($user);

    expect(resolve(PxUser::class)->logout())->toBe(['success' => true])
        ->and($user->hasValidPxUserToken())->toBeFalse();

    FakePxUser::session($user);
    FakePxUser::fail(PxUserEndpoint::Logout, new ConnectionException('px unreachable'));

    expect(fn () => resolve(PxUser::class)->logout())->toThrow(ConnectionException::class);
});

test('logs the next login user in over oidc and records the exchange', function () {
    $user = User::factory()->create(['px_user_id' => 'user-1']);
    FakePxUser::nextLogin($user);
    $client = resolve(PxUserOidcClient::class);
    $client->setClientId('client-1');

    $loggedIn = (new PxUser(resolve(PxUserClient::class), $client, resolve(PxUserTokens::class)))
        ->oidcLogin('the-code', 'the-verifier');

    expect($loggedIn->is($user))->toBeTrue()
        ->and(PxUserToken::query()->forAuthenticatable($user)->count())->toBe(1);
    FakePxUser::assertSent(PxUserEndpoint::OidcToken, fn (array $call) => $call === [
        'client_id' => 'client-1',
        'code' => 'the-code',
        'code_verifier' => 'the-verifier',
    ]);
});

test('searches users by name and answers no match with 404', function () {
    $this->actingAs(User::factory()->create(['px_user_id' => 'viewer']));
    FakePxUser::user(User::factory()->create(['px_user_id' => 'ada']), ['firstname' => 'Ada', 'lastname' => 'Lovelace']);
    $client = resolve(PxUserClient::class);

    expect(collect($client->getUsers('ada love'))->pluck('id')->all())->toBe(['ada'])
        ->and(fn () => $client->getUsers('grace'))->toThrow(fn (RequestException $e) => expect($e->response->status())->toBe(404));
});

test('answers the admin lookup for users PX knows and 404 for others', function () {
    User::factory()->create(['px_user_id' => 'known']);
    FakePxUser::forget('gone');

    $client = resolve(PxUserAdminClient::class);

    expect($client->getUser('known', 'tenant', 'domain')?->id)->toBe('known')
        ->and(fn () => $client->getUser('gone', 'tenant', 'domain'))
        ->toThrow(fn (RequestException $e) => expect($e->response->status())->toBe(404));
    FakePxUser::assertSent(PxUserEndpoint::AdminUser, fn (array $call) => $call['m2m_credentials'] === 'test:secret', times: 2);
});

test('resolves PxUser anew, so client hooks see the current config', function () {
    app()->resolving(PxUserOidcClient::class, fn (PxUserOidcClient $client) => $client->setClientId(config('test.client_id')));
    config(['test.client_id' => 'before']);
    FakePxUser::nextLogin(User::factory()->create(['px_user_id' => 'user-1']));
    config(['test.client_id' => 'after']);

    resolve(PxUser::class)->oidcLogin('the-code', 'the-verifier');

    FakePxUser::assertSent(PxUserEndpoint::OidcToken, fn (array $call) => $call['client_id'] === 'after');
});

test('issues tokens without storing them, also for a PX user the app has not seen', function () {
    $tokens = FakePxUser::tokens('px-only');

    expect(PxUserToken::query()->count())->toBe(0)
        ->and(resolve(PxUserClient::class)->refreshTokens($tokens['refresh_token'])['access_token'])->not->toBe($tokens['access_token']);
});

test('refreshes tokens the app stored from a login request', function () {
    $user = User::factory()->create(['px_user_id' => 'user-1']);
    FakePxUser::nextLogin($user);
    resolve(PxUser::class)->login(makeTokenData(['refresh_token' => 'refresh-from-the-spa']));

    $refreshed = resolve(PxUser::class)->refresh('refresh-from-the-spa');

    expect($refreshed['access_token'])->toStartWith('fake-access-token-');
});

test('adds a user to PX without caching their attributes', function () {
    $this->actingAs($user = User::factory()->create(['px_user_id' => 'uncached']));
    FakePxUser::user($user, ['firstname' => 'Ada'], cache: false);

    expect(Cache::has($user->cachedAttributeKey()))->toBeFalse()
        ->and($user->firstname)->toBe('Ada');
    FakePxUser::assertSent(PxUserEndpoint::User, times: 1);
});

test('restore lets the real clients send requests again', function () {
    FakePxUser::fake();
    FakePxUser::restore();

    expect(resolve(PxUser::class))->not->toBeInstanceOf(FakePxUser::class)
        ->and(resolve(PxUserClient::class))->not->toBeInstanceOf(FakePxUserClient::class)
        ->and(app()->bound('px-user.fake.enabled'))->toBeFalse();
});

test('builds payloads from snake_case or camelCase overrides', function () {
    $payload = PxUserData::payload(['id' => 'user-1', 'preferredUsername' => 'ada', 'tenant_code' => 'tenant']);

    expect($payload)->toHaveKeys(['id', 'email', 'preferred_username', 'tenant_code', 'roles', 'locale'])
        ->not->toHaveKey('capabilities')
        ->and($payload['preferred_username'])->toBe('ada')
        ->and($payload['email'])->toBe('user-1@example.com')
        ->and(PxUserDataWithPermissions::fake(['preferred_username' => 'ada', 'tenantCode' => 'tenant'])->tenantCode)->toBe('tenant');
});

test('answers PX calls while the PxUser service is mocked', function () {
    FakePxUser::fake();
    $this->mock(PxUser::class);
    $user = User::factory()->create(['px_user_id' => 'mocked']);
    $this->actingAs($user);

    expect($user->email)->toBe('mocked@example.com')
        ->and(FakePxUser::actAs($user)?->is($user))->toBeTrue();

    test('passes the faked user data to the refresh event', function () {
        Event::fake([PxUserTokenRefreshEvent::class]);
        $user = FakePxUser::actAs(User::factory()->create(['px_user_id' => 'actor-1']), ['firstname' => 'Refreshed']);
        $this->travel(2)->hours();

        expect(resolve(PxUser::class)->refreshSession($user))->toBeTrue();

        FakePxUser::assertSent(PxUserEndpoint::RefreshTokens, times: 1);
        Event::assertDispatched(PxUserTokenRefreshEvent::class, fn (PxUserTokenRefreshEvent $event) => $event->userData->id === 'actor-1'
            && $event->userData->firstname === 'Refreshed');
    });
});
