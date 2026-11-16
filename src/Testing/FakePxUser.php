<?php

namespace mindtwo\PxUserLaravel\Testing;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use mindtwo\PxUserLaravel\Contracts\PxUser as ContractsPxUser;
use mindtwo\PxUserLaravel\DataTransfer\PxUserData;
use mindtwo\PxUserLaravel\DataTransfer\PxUserDataWithPermissions;
use mindtwo\PxUserLaravel\Helper\Utils;
use mindtwo\PxUserLaravel\Http\Client\PxUserAdminClient;
use mindtwo\PxUserLaravel\Http\Client\PxUserClient;
use mindtwo\PxUserLaravel\Http\Client\PxUserOidcClient;
use mindtwo\PxUserLaravel\Models\PxUserToken;
use mindtwo\PxUserLaravel\PxUser;
use mindtwo\PxUserLaravel\Services\PxUserAdminCachedApiService;
use mindtwo\PxUserLaravel\Services\PxUserCachedApiService;
use mindtwo\PxUserLaravel\Services\PxUserTokens;
use mindtwo\PxUserLaravel\Testing\Clients\FakePxUserAdminClient;
use mindtwo\PxUserLaravel\Testing\Clients\FakePxUserClient;
use mindtwo\PxUserLaravel\Testing\Clients\FakePxUserOidcClient;
use PHPUnit\Framework\Assert;
use RuntimeException;
use Throwable;

/**
 * Fakes the PX User API for tests: the package's clients answer from a set of PX users
 * kept per test, while PxUser, its services, middleware and the Scout engine run as usual.
 */
class FakePxUser extends PxUser
{
    /**
     * The fake clients bound in place of the real ones.
     */
    private const CLIENTS = [
        PxUserClient::class => FakePxUserClient::class,
        PxUserAdminClient::class => FakePxUserAdminClient::class,
        PxUserOidcClient::class => FakePxUserOidcClient::class,
    ];

    /**
     * Fake the PX User API for the current test. Does nothing when it is already faked.
     *
     * @throws RuntimeException if not running unit tests
     */
    public static function fake(): self
    {
        if (app()->bound('px-user.fake.enabled') && app()->bound(FakePxUserState::class)) {
            return app(self::class);
        }

        if (! app()->runningUnitTests()) {
            throw new RuntimeException('FakePxUser can only be used during unit tests.');
        }

        $state = new FakePxUserState;
        $previousBindings = [];

        foreach (self::CLIENTS as $client => $fake) {
            $previousBindings[$client] = app()->getBindings()[$client] ?? null;
            app()->bind($client, fn () => new $fake($state));
        }

        // Resolved anew each time like PxUser, so resolving() hooks on the clients see the current config
        $previousBindings[PxUser::class] = app()->getBindings()[PxUser::class] ?? null;
        app()->bind(PxUser::class, fn ($app) => $app->build(self::class));

        app()->instance(FakePxUserState::class, $state);
        app()->instance('px-user.fake.previous-bindings', $previousBindings);
        app()->instance('px-user.fake.enabled', true);
        app()->forgetInstance(PxUserCachedApiService::class);
        app()->forgetInstance(PxUserAdminCachedApiService::class);
        app()->forgetInstance(PxUser::class);

        return app(self::class);
    }

    /**
     * Stop faking the PX User API, so the real clients send HTTP requests again (e.g. to Http::fake()).
     *
     * Every other helper fakes the API again, so call it after the test's PX setup.
     */
    public static function restore(): void
    {
        if (app()->bound('px-user.fake.previous-bindings')) {
            foreach (app('px-user.fake.previous-bindings') as $abstract => $binding) {
                app()->offsetUnset($abstract);

                if ($binding !== null) {
                    app()->bind($abstract, $binding['concrete'], $binding['shared']);
                }
            }
        }

        foreach ([
            PxUser::class,
            self::class,
            FakePxUserState::class,
            PxUserCachedApiService::class,
            PxUserAdminCachedApiService::class,
            'px-user.fake.enabled',
            'px-user.fake.previous-bindings',
        ] as $abstract) {
            app()->forgetInstance($abstract);
        }
    }

    /**
     * Fake the API, add the user to PX and give them a valid PX session; their next login resolves to them.
     *
     * Does not authenticate the user; call actingAs() for that.
     *
     * @param  PxUserDataWithPermissions|array<string, mixed>  $data  Overrides for the user's PX data, in snake_case or camelCase
     * @return Model|null The user, or null when it does not implement the PxUser contract
     *
     * @throws RuntimeException if not running unit tests
     */
    public static function actAs(Authenticatable $user, PxUserDataWithPermissions|array $data = []): ?Model
    {
        if (! $user instanceof ContractsPxUser) {
            return null;
        }

        self::nextLogin($user, $data);
        self::session($user);

        $model = FakePxUserState::localUser($user->getPxUserId());

        return $model instanceof Model ? $model : null;
    }

    /**
     * Add the user to PX with the given data and cache their PX attributes (email, names, username).
     *
     * Calling it again for the same user merges the new data into what PX already knows.
     *
     * @param  Arrayable<string, mixed>|array<string, mixed>  $data  Overrides for the user's PX data, in snake_case or camelCase
     * @param  bool  $cache  Whether to cache the attributes; without, the app loads them from the fake on first read
     */
    public static function user(ContractsPxUser $user, Arrayable|array $data = [], bool $cache = true): PxUserDataWithPermissions
    {
        $payload = self::state()->putUser($user, PxUserPayload::normalize($data));

        if (! $cache) {
            Cache::forget(Utils::getPxUserCacheKey($user));

            return PxUserDataWithPermissions::from($payload);
        }

        Cache::put(
            Utils::getPxUserCacheKey($user),
            PxUserData::from($payload),
            now()->addMinutes(config('px-user.px_user_cache_time', 120)),
        );

        return PxUserDataWithPermissions::from($payload);
    }

    /**
     * Seed the PX attributes of a user that is not the authenticated one.
     *
     * @param  array<string, mixed>  $overrides  Overrides for the user's PX data, in snake_case or camelCase
     */
    public static function fakePxUserData(ContractsPxUser $user, array $overrides = []): void
    {
        self::user($user, $overrides);
    }

    /**
     * Remove the user from PX and from the attribute cache, so PX no longer knows them.
     */
    public static function forget(ContractsPxUser|string $user): void
    {
        self::state()->forget($user instanceof ContractsPxUser ? $user->getPxUserId() : $user);

        Cache::forget(Utils::getPxUserCacheKey($user));
    }

    /**
     * Add the user to PX and let the next login (an unknown access token or OIDC code) resolve to them.
     *
     * @param  Arrayable<string, mixed>|array<string, mixed>  $data  Overrides for the user's PX data, in snake_case or camelCase
     */
    public static function nextLogin(ContractsPxUser $user, Arrayable|array $data = []): PxUserDataWithPermissions
    {
        $userData = self::user($user, $data);

        self::state()->setNextLogin($user->getPxUserId());

        return $userData;
    }

    /**
     * Store a valid PX session (an access token for an hour, a refresh token for a week) for the user.
     *
     * @return array{access_token: string, access_token_expiration_utc: string, refresh_token: string, refresh_token_expiration_utc: string}
     */
    public static function session(ContractsPxUser&Authenticatable $user): array
    {
        $tokens = self::state()->issueTokens($user->getPxUserId());

        app(PxUserTokens::class)->save($user, $tokens);

        return $tokens;
    }

    /**
     * Issue a token pair for the user without storing it, e.g. for a login or refresh request.
     *
     * A PX user id the fake does not know yet is added to PX with defaults.
     *
     * @return array{access_token: string, access_token_expiration_utc: string, refresh_token: string, refresh_token_expiration_utc: string}
     */
    public static function tokens(ContractsPxUser|string $user): array
    {
        $state = self::state();

        if ($user instanceof ContractsPxUser) {
            $state->putUser($user);
        } elseif ($state->find($user) === null) {
            $state->put($user, []);
        }

        return $state->issueTokens($user instanceof ContractsPxUser ? $user->getPxUserId() : $user);
    }

    /**
     * Replace the user's PX session with one whose access token has expired.
     *
     * @param  bool  $refreshable  Whether the refresh token is still valid, so the session can be refreshed
     * @return array{access_token: string, access_token_expiration_utc: string, refresh_token: string, refresh_token_expiration_utc: string}
     */
    public static function expireSession(ContractsPxUser&Authenticatable $user, bool $refreshable = true): array
    {
        $tokens = [
            ...self::state()->issueTokens($user->getPxUserId()),
            'access_token_expiration_utc' => now()->subMinute()->startOfSecond()->toIso8601String(),
        ];

        if (! $refreshable) {
            $tokens['refresh_token_expiration_utc'] = now()->subMinute()->startOfSecond()->toIso8601String();
        }

        // Rows stored in the same second tie on latest(), so the previous session could still be read
        PxUserToken::query()->forAuthenticatable($user)->delete();
        app(PxUserTokens::class)->save($user, $tokens);

        return $tokens;
    }

    /**
     * Let the endpoint fail until succeed() is called: a status code throws the HTTP client's
     * RequestException, an exception (e.g. a ConnectionException) is thrown as given.
     */
    public static function fail(PxUserEndpoint $endpoint, int|Throwable $with = 500): void
    {
        self::state()->fail($endpoint, $with);
    }

    /**
     * Let a failing endpoint answer normally again.
     */
    public static function succeed(PxUserEndpoint $endpoint): void
    {
        self::state()->succeed($endpoint);
    }

    /**
     * Get the calls made to the endpoint, in order, with the context the real client would send.
     *
     * @return list<array<string, mixed>>
     */
    public static function recorded(PxUserEndpoint $endpoint): array
    {
        return self::state()->recorded($endpoint);
    }

    /**
     * Assert the endpoint was called, optionally matching a callback or a number of times.
     *
     * @param  (Closure(array<string, mixed>): bool)|null  $callback
     */
    public static function assertSent(PxUserEndpoint $endpoint, ?Closure $callback = null, ?int $times = null): void
    {
        $calls = self::matching($endpoint, $callback);

        if ($times === null) {
            Assert::assertNotEmpty($calls, "No matching call to PX User [{$endpoint->value}] was recorded.");

            return;
        }

        Assert::assertCount($times, $calls, "PX User [{$endpoint->value}] was not called the expected number of times.");
    }

    /**
     * Assert the endpoint was not called, or not with a call matching the callback.
     *
     * @param  (Closure(array<string, mixed>): bool)|null  $callback
     */
    public static function assertNotSent(PxUserEndpoint $endpoint, ?Closure $callback = null): void
    {
        Assert::assertEmpty(self::matching($endpoint, $callback), "An unexpected call to PX User [{$endpoint->value}] was recorded.");
    }

    /**
     * Clear all fake data and stop faking.
     *
     * @deprecated Use FakePxUser::restore(); this also flushes the whole cache.
     */
    public function clear(): void
    {
        Cache::flush();

        static::restore();
    }

    /**
     * Get the calls to the endpoint that match the callback.
     *
     * @param  (Closure(array<string, mixed>): bool)|null  $callback
     * @return list<array<string, mixed>>
     */
    private static function matching(PxUserEndpoint $endpoint, ?Closure $callback): array
    {
        return array_values(array_filter(
            self::state()->recorded($endpoint),
            fn (array $call) => $callback === null || $callback($call),
        ));
    }

    /**
     * Get the current test's fake state, faking the API first when needed.
     */
    private static function state(): FakePxUserState
    {
        self::fake();

        return app(FakePxUserState::class);
    }
}
