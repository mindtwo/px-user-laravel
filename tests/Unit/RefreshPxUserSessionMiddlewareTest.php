<?php

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use mindtwo\PxUserLaravel\Contracts\PxUser as ContractsPxUser;
use mindtwo\PxUserLaravel\Http\Middleware\RefreshPxUserSession;
use mindtwo\PxUserLaravel\Models\PxUserToken;
use mindtwo\PxUserLaravel\PxUser;
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

/**
 * A request with a session that resolves its user through the auth guards, as on a web route.
 */
function sessionRequest(): Request
{
    $request = Request::create('/test', 'GET');
    $request->setLaravelSession(app('session.store'));
    $request->setUserResolver(fn (?string $guard = null) => auth()->guard($guard)->user());

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

test('ends the web session and passes the request on as a guest when the session cannot be refreshed', function () {
    Http::fake(['*v1/refresh-tokens' => Http::response(status: 401)]);
    $user = User::factory()->create(['px_user_id' => 'user-123']);
    (new PxUserTokens)->save($user, makeTokenData([
        'access_token_expiration_utc' => now()->subMinute()->toIso8601String(),
    ]));
    $this->actingAs($user, 'web');
    $request = sessionRequest();
    $request->session()->put('kept', 'value');
    $csrfToken = $request->session()->token();

    $userSeenByNext = $user;
    resolve(RefreshPxUserSession::class)->handle($request, function (Request $request) use (&$userSeenByNext) {
        $userSeenByNext = $request->user();

        return response('OK');
    });

    expect($userSeenByNext)->toBeNull()
        ->and(auth()->guard('web')->guest())->toBeTrue()
        ->and($request->session()->has('kept'))->toBeFalse()
        ->and($request->session()->token())->not->toBe($csrfToken);
});

test('calls failedRefresh after ending the session when the session cannot be refreshed', function () {
    Http::fake(['*v1/refresh-tokens' => Http::response(status: 401)]);
    $user = User::factory()->create(['px_user_id' => 'user-123']);
    (new PxUserTokens)->save($user, makeTokenData([
        'access_token_expiration_utc' => now()->subMinute()->toIso8601String(),
    ]));
    $this->actingAs($user, 'web');

    $middleware = new class(resolve(PxUser::class), resolve(AuthFactory::class)) extends RefreshPxUserSession
    {
        public ?ContractsPxUser $failedFor = null;

        public ?bool $wasGuest = null;

        protected function failedRefresh(Request $request, ContractsPxUser $user, ?string $guard): void
        {
            $this->failedFor = $user;
            $this->wasGuest = auth()->guard($guard)->guest();
        }
    };

    $middleware->handle(sessionRequest(), fn () => response('OK'));

    expect($middleware->failedFor?->is($user))->toBeTrue()
        ->and($middleware->wasGuest)->toBeTrue();
});

test('does not call failedRefresh when the session is refreshed', function () {
    Http::fake();
    $user = User::factory()->create(['px_user_id' => 'user-123']);
    (new PxUserTokens)->save($user, makeTokenData());
    $this->actingAs($user, 'web');

    $middleware = new class(resolve(PxUser::class), resolve(AuthFactory::class)) extends RefreshPxUserSession
    {
        public bool $called = false;

        protected function failedRefresh(Request $request, ContractsPxUser $user, ?string $guard): void
        {
            $this->called = true;
        }
    };

    expect($middleware->handle(sessionRequest(), fn () => response('OK'))->getContent())->toBe('OK')
        ->and($middleware->called)->toBeFalse()
        ->and(auth()->guard('web')->check())->toBeTrue();
});

test('aborts with 401 when the session cannot be refreshed on a guard it cannot log out of', function () {
    Http::fake(['*v1/refresh-tokens' => Http::response(status: 401)]);
    config(['auth.guards.token' => ['driver' => 'token', 'provider' => 'users']]);
    $user = User::factory()->create(['px_user_id' => 'user-123']);
    (new PxUserTokens)->save($user, makeTokenData([
        'access_token_expiration_utc' => now()->subMinute()->toIso8601String(),
    ]));
    auth()->guard('token')->setUser($user);

    expect(fn () => resolve(RefreshPxUserSession::class)->handle(sessionRequest(), fn () => response('OK'), 'token'))
        ->toThrow(fn (HttpException $e) => expect($e->getStatusCode())->toBe(401));
});
