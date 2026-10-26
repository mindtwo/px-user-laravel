<?php

use Illuminate\Auth\Events\Login;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use mindtwo\PxUserLaravel\Events\PxUserTokenRefreshEvent;
use mindtwo\PxUserLaravel\Models\PxUserToken;
use mindtwo\PxUserLaravel\PxUser;
use mindtwo\PxUserLaravel\Services\PxUserTokens;
use mindtwo\PxUserLaravel\Tests\Fake\User;

beforeEach(function () {
    config(['px-user.user_model' => User::class]);
    config(['px-user.px_user_id' => 'px_user_id']);
    config(['px-user.refresh_leeway' => 60]);
});

function refreshLockKey(User $user): string
{
    return 'px-user:refresh:'.md5(User::class).':'.$user->getAuthIdentifier();
}

function userWithToken(array $tokenData): User
{
    $user = User::factory()->create(['px_user_id' => 'user-123']);
    (new PxUserTokens)->save($user, $tokenData);

    return $user;
}

function fakePxRefresh(string $accessToken = 'refreshed'): void
{
    Http::fake([
        '*v1/refresh-tokens' => Http::response([
            'response' => makeTokenData(['access_token' => $accessToken]),
        ]),
    ]);
}

test('refreshes an expired token and dispatches the refresh event', function () {
    Event::fake([PxUserTokenRefreshEvent::class, Login::class]);
    $user = userWithToken(makeTokenData([
        'access_token_expiration_utc' => now()->subMinute()->toIso8601String(),
        'refresh_token' => 'refresh-abc',
    ]));
    fakePxRefresh();

    expect(resolve(PxUser::class)->refreshSession($user))->toBeTrue()
        ->and((new PxUserTokens)->accessToken($user))->toBe('refreshed');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'v1/refresh-tokens')
        && $request->hasHeader('Authorization', 'Bearer refresh-abc'));
    Event::assertDispatched(PxUserTokenRefreshEvent::class, fn (PxUserTokenRefreshEvent $event) => $event->newAccessToken === 'refreshed'
        && $event->user->is($user));
    Event::assertNotDispatched(Login::class);
});

test('refreshes a token that expires within the leeway', function () {
    $user = userWithToken(makeTokenData([
        'access_token_expiration_utc' => now()->addSeconds(30)->toIso8601String(),
    ]));
    fakePxRefresh();

    expect(resolve(PxUser::class)->refreshSession($user))->toBeTrue();

    Http::assertSentCount(1);
});

test('does not lock or call px while the token is fresh', function () {
    Sleep::fake();
    Http::fake();
    $user = userWithToken(makeTokenData());
    Cache::lock(refreshLockKey($user), 60)->get();

    expect(resolve(PxUser::class)->refreshSession($user))->toBeTrue();

    Http::assertNothingSent();
    Sleep::assertNeverSlept();
});

test('refreshes once when another request refreshed while waiting for the lock', function () {
    Http::fake();
    $user = userWithToken(makeTokenData([
        'access_token_expiration_utc' => now()->subMinute()->toIso8601String(),
    ]));
    Cache::lock(refreshLockKey($user), 60)->get();

    Sleep::fake(syncWithCarbon: true);
    Sleep::whenFakingSleep(function () use ($user) {
        (new PxUserTokens)->save($user, makeTokenData(['access_token' => 'other-request']));
        Cache::lock(refreshLockKey($user))->forceRelease();
    });

    expect(resolve(PxUser::class)->refreshSession($user))->toBeTrue()
        ->and((new PxUserTokens)->accessToken($user))->toBe('other-request');

    Http::assertNothingSent();
});

test('returns false when the lock stays held and the token is still expired', function () {
    Http::fake();
    Sleep::fake(syncWithCarbon: true);
    $user = userWithToken(makeTokenData([
        'access_token_expiration_utc' => now()->subMinute()->toIso8601String(),
    ]));
    Cache::lock(refreshLockKey($user), 60)->get();

    expect(resolve(PxUser::class)->refreshSession($user))->toBeFalse();

    Http::assertNothingSent();
});

test('returns false without http when the refresh token is missing or expired', function (array $refreshData) {
    Http::fake();
    $tokenData = array_merge([
        'access_token' => 'expired',
        'access_token_expiration_utc' => now()->subMinute()->toIso8601String(),
    ], $refreshData);
    $user = userWithToken($tokenData);

    expect(resolve(PxUser::class)->refreshSession($user))->toBeFalse();

    Http::assertNothingSent();
})->with([
    'missing' => [[]],
    'expired' => fn () => [[
        'refresh_token' => 'refresh-abc',
        'refresh_token_expiration_utc' => now()->subMinute()->toIso8601String(),
    ]],
]);

test('returns false when px rejects the refresh token', function () {
    Http::fake(['*v1/refresh-tokens' => Http::response(status: 401)]);
    $user = userWithToken(makeTokenData([
        'access_token_expiration_utc' => now()->subMinute()->toIso8601String(),
    ]));

    expect(resolve(PxUser::class)->refreshSession($user))->toBeFalse()
        ->and(PxUserToken::query()->forAuthenticatable($user)->count())->toBe(1);
});

test('keeps a still valid token usable when the refresh fails', function () {
    Http::fake(['*v1/refresh-tokens' => Http::response(status: 500)]);
    $user = userWithToken(makeTokenData([
        'access_token_expiration_utc' => now()->addSeconds(30)->toIso8601String(),
    ]));

    expect(resolve(PxUser::class)->refreshSession($user))->toBeTrue();

    Http::assertSentCount(1);
});

test('keeps a still valid token usable when the lock cannot be acquired', function () {
    Http::fake();
    Sleep::fake(syncWithCarbon: true);
    $user = userWithToken(makeTokenData([
        'access_token_expiration_utc' => now()->addSeconds(30)->toIso8601String(),
    ]));
    Cache::lock(refreshLockKey($user), 60)->get();

    expect(resolve(PxUser::class)->refreshSession($user))->toBeTrue();

    Http::assertNothingSent();
});

test('keeps the lock for a refresh as slow as the client allows', function () {
    config(['px-user.apiClient.timeout' => 30, 'px-user.apiClient.retries' => 3]);
    $user = userWithToken(makeTokenData([
        'access_token_expiration_utc' => now()->subMinute()->toIso8601String(),
    ]));
    $lockHeldDuringSlowCall = null;

    Http::fake(['*v1/refresh-tokens' => function () use ($user, &$lockHeldDuringSlowCall) {
        $this->travel(91)->seconds();
        $lockHeldDuringSlowCall = ! Cache::lock(refreshLockKey($user), 1)->get();

        return Http::response(['response' => makeTokenData()]);
    }]);

    expect(resolve(PxUser::class)->refreshSession($user))->toBeTrue()
        ->and($lockHeldDuringSlowCall)->toBeTrue();
});
