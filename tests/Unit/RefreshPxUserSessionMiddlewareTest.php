<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use mindtwo\PxUserLaravel\Http\Middleware\RefreshPxUserSession;
use mindtwo\PxUserLaravel\Models\PxUserToken;
use mindtwo\PxUserLaravel\Services\PxUserTokens;
use mindtwo\PxUserLaravel\Tests\Fake\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    config(['px-user.user_model' => User::class]);
    config(['px-user.px_user_id' => 'px_user_id']);
});

function requestAs(?object $user): Request
{
    $request = Request::create('/test', 'GET');
    $request->setUserResolver(fn () => $user);

    return $request;
}

function runRefreshMiddleware(Request $request): string
{
    return resolve(RefreshPxUserSession::class)
        ->handle($request, fn () => response('OK'))
        ->getContent();
}

test('passes guests through', function () {
    Http::fake();

    expect(runRefreshMiddleware(requestAs(null)))->toBe('OK');

    Http::assertNothingSent();
});

test('passes a user with a fresh token without calling px', function () {
    Http::fake();
    $user = User::factory()->create(['px_user_id' => 'user-123']);
    (new PxUserTokens)->save($user, makeTokenData());

    expect(runRefreshMiddleware(requestAs($user)))->toBe('OK');

    Http::assertNothingSent();
});

test('refreshes an expired token and passes the request', function () {
    Http::fake([
        '*v1/refresh-tokens' => Http::response(['response' => makeTokenData(['access_token' => 'refreshed'])]),
    ]);
    $user = User::factory()->create(['px_user_id' => 'user-123']);
    (new PxUserTokens)->save($user, makeTokenData([
        'access_token_expiration_utc' => now()->subMinute()->toIso8601String(),
    ]));

    expect(runRefreshMiddleware(requestAs($user)))->toBe('OK')
        ->and(PxUserToken::query()->forAuthenticatable($user)->valid()->first()?->token('access_token'))->toBe('refreshed');
});

test('aborts with 401 when the session cannot be refreshed', function () {
    Http::fake(['*v1/refresh-tokens' => Http::response(status: 401)]);
    $user = User::factory()->create(['px_user_id' => 'user-123']);
    (new PxUserTokens)->save($user, makeTokenData([
        'access_token_expiration_utc' => now()->subMinute()->toIso8601String(),
    ]));

    expect(fn () => runRefreshMiddleware(requestAs($user)))
        ->toThrow(fn (HttpException $e) => expect($e->getStatusCode())->toBe(401));
});
