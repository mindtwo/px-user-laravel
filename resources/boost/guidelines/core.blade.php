{{-- PX User Laravel Guidelines for AI Code Assistants --}}
{{-- Package: mindtwo/px-user-laravel | https://github.com/mindtwo/px-user-laravel --}}

## PX User (mindtwo/px-user-laravel)

- `mindtwo/px-user-laravel` authenticates users against the external PX User identity provider (SSO). The identity (email, name, roles, products/licenses) lives remotely — the local user model is only an anchor row keyed by the PX User ID.
- Namespace: `mindtwo\PxUserLaravel\`. The service provider is auto-discovered; config lives in `config/px-user.php`.

### User model requirements

- The authenticatable model must implement `mindtwo\PxUserLaravel\Contracts\PxUser` and use `mindtwo\PxUserLaravel\Traits\HasPxUser`, and must be registered as `px-user.user_model` in config.
- The local column holding the PX User ID is configured via `px-user.px_user_id` (default `px_user_id`). Apps may map it to another column (e.g. `uuid`) — always read it through `$user->getPxUserId()`, never assume the column name.
- `email`, `firstname`, `lastname` and `preferredUsername` are NOT database columns. `HasPxUser` resolves them from a cache backed by the PX User API. Never assign them on the model, never query them with `where()`/`whereLike()`, and never add them to `$fillable` or factories.
- If the cached attributes cannot be resolved in a web request, the trait calls `abort(401, 'Unauthenticated')`. In console context it falls back to the M2M admin client instead.
- Useful contract methods: `getPxUserId()`, `getPxUserTenantCode()`, `getPxUserDomainCode()`, `getPxUserAccessToken()`, `hasValidPxUserToken()`.

### Authentication flows

All flows go through the `mindtwo\PxUserLaravel\PxUser` service (constructor injection or `resolve(PxUser::class)`). There is no facade.

- `login(array $tokenData)` — token-based login. Expects `access_token` + `access_token_expiration_utc` (and optionally `refresh_token` + `refresh_token_expiration_utc`), typically posted by the `@mindtwo/px-user-widgets` frontend widget. Validates the data, fetches the remote user with permissions, `firstOrCreate`s the local user, persists a `PxUserToken` row, calls `auth()->login()` and fires `PxUserLoginEvent`. Returns the user model or `false`; throws on invalid token data.
- `oidcLogin(string $code, string $codeVerifier)` — OIDC Authorization Code + PKCE flow. Exchanges the code via `PxUserOidcClient`, then delegates to `login()`. A failed exchange throws an `Illuminate\Http\Client\RequestException` (map to 422); `false` means no user could be resolved (map to 401).
- `refresh(string $refreshToken)` — refreshes the PX tokens, saves them and logs the user in as a side effect. It returns the raw token array, NOT the user — read `auth()->user()` afterwards.
- `logout()` — invalidates the remote PX session and the local token row for `auth()->user()`. Treat the remote call as best-effort (wrap in try/catch) and always delete the app's own session/API tokens afterwards.
- `resolveByToken(array $tokenData)` — resolves `[PxUserDataWithPermissions, user|false]` without logging in.
- Common consumer pattern: after `login()`/`oidcLogin()`, mint an app token (e.g. Sanctum) with `expiresAt: Carbon::parse($tokenData['access_token_expiration_utc'])` so the app session tracks the PX session.

### PxUserLoginEvent — sync remote data locally

- `PxUserLoginEvent` carries `public PxUser $user`, `public PxUserData|PxUserDataWithPermissions $userData` and `public bool $isFirstLogin`. The third constructor argument is a boolean flag, not a token string.
- A listener on this event is the canonical place to sync remote state into local columns: `isEnabled`, `isConfirmed`, `tenantCode`, `domainCode`, `products`/`productsValidity` (licenses), `roles` (keyed by product code) and `correlatedId` (link to a correlated external record, e.g. an employee UUID).
- `PxUserDataWithPermissions` is a spatie/laravel-data DTO with snake_case input mapping. Fields include: `id`, `correlatedId`, `email`, `preferredUsername`, `tenantCode`, `domainCode`, `isEnabled`, `isConfirmed`, `suspended`, `isHuman`, `firstname`, `lastname`, `gender`, `lastLoginAt`, `lastActivityAt`, `locale`, `products`, `productsValidity`, `capabilities`, `roles`.

### Token storage — PxUserTokens & PxUserToken

- `mindtwo\PxUserLaravel\Services\PxUserTokens` manages the `px_user_tokens` table (`Models\PxUserToken`, morphs to the authenticatable): `current($user)` (raw token array), `save($user, $tokenData)`, `invalidate($user)`, `isCurrentTokenValid($user)`, `canRefreshCurrentToken($user)`, `accessToken($user)`, `expiresAt($user)`, `refreshToken($user)`, `refreshTokenValidUntil($user)`.
- `current()`, `accessToken()` and `expiresAt()` throw a `RuntimeException` when no valid token row exists — guard accordingly.
- `expiresAt($user)` is a good TTL for caches that should live exactly as long as the PX session.
- Schedule pruning: `$schedule->command('model:prune', ['--model' => [PxUserToken::class]])->daily();` — retention is controlled by `px-user.token_retention_days` (default 30).

### HTTP clients

- `Http\Client\PxUserClient` — user-context client (bearer access token of the current user): `getUser()`, `getUsersDetails()`, `getUserWithPermissions()`, `getUsers($names, $productContext)`, `refreshTokens()`, `logout()`, `checkEipConnection()`; configurable via `setTenantCode()`, `setDomainCode()`, `setAccessToken()`.
- `Http\Client\PxUserAdminClient` — machine-to-machine client. `withM2mCredentials('clientId:clientSecret')` sets the `x-m2m-authorization` header; `getUser(string $pxUserId)` fetches any user. Credentials default to `px-user.m2m_credentials`.
- `Http\Client\PxUserOidcClient` — token exchange (`exchangeToken($code, $codeVerifier)`) with `setTenantCode()`, `setDomainCode()`, `setClientId()`, `setRedirectUri()`.
- Prefer the cached wrappers over direct client calls when reading user data: `Services\PxUserCachedApiService` (current user context) and `Services\PxUserAdminCachedApiService` (M2M). TTL comes from `px-user.px_user_cache_time` (minutes).
- Multi-tenant apps: inject per-tenant credentials via container `resolving()` hooks instead of hardcoding config:

```php
$this->app->resolving(PxUserOidcClient::class, function (PxUserOidcClient $client) {
    $platform = $this->app->make(PlatformResolver::class)->getCurrentPlatform();
    $client->setTenantCode($platform->px_user_tenant);
    $client->setDomainCode($platform->px_user_domain);
    [$clientId] = explode(':', $platform->px_user_secret); // "clientId:clientSecret"
    $client->setClientId($clientId);
    $client->setRedirectUri($platform->getRedirectUri());
});
```

- Caution: the second parameter of a `resolving()` callback is the container, not one of your services — type-hinting it as a domain service silently breaks resolution.

### Middleware

- `Http\Middleware\CheckPxUserSession` aborts with 401 when the authenticated user no longer has a valid PX User token. Append it to route groups that must stay in sync with the PX session (e.g. admin panels). It passes through unauthenticated requests and non-PxUser users.

### Scout user search

- The package registers a Scout engine named `px-user` that searches users by name through the PX User API. Route a model to it via:

```php
public function searchableUsing(): Engine
{
    return app(EngineManager::class)->engine('px-user');
}
```

- Search users with `User::search('name')` — never with `whereLike` on local columns (names are not stored locally). The product context for searches is `px-user.scout.product_code`.

### Testing with FakePxUser

- Never let tests hit the real PX User API. Use `mindtwo\PxUserLaravel\Testing\FakePxUser` (unit-test context only — it throws outside of tests).
- `FakePxUser::fake()` swaps the `PxUser` service and mocks `PxUserCachedApiService`, so `login()`/`resolveByToken()` produce fake data without HTTP calls. Call it per test file (e.g. in `beforeEach`) rather than globally, so real-flow tests can still exercise the actual service against `Http::fake()`.
- `FakePxUser::actAs($user)` fakes the PX data for that user AND persists a fake `PxUserToken` row. Combine with the framework's `actingAs()`:

```php
FakePxUser::actAs($user);
$this->actingAs($user->fresh());
```

- Only the acting user's data is faked. When a test asserts proxied attributes of another user (e.g. a mail recipient's email), seed that user's cache explicitly with `FakePxUser::fakePxUserData($otherUser, ['email' => '...'])` — otherwise those attributes resolve to `null`.
- Build DTOs for listeners/unit tests with the testing factories: `Testing\PxUserData::fake([...])` and `Testing\PxUserDataWithPermissions::fake([...])`.
- Symptom of a missing fake: an opaque `abort(401, 'Unauthenticated')` from `HasPxUser::beforeCachedAttributeLoad`, or remote attributes returning `null`.
- Tests for the real login/refresh flows should `Http::fake()` the PX endpoints instead (e.g. `'*/user-with-permissions*'`, `'*/refresh-tokens*'`, `'{tenant}:{domain}/oidc/v1.0/token'`).

### Configuration

- Key config values (`config/px-user.php`): `user_model`, `px_user_id`, `stage`, `tenant`, `domain`, `m2m_credentials` (`clientId:clientSecret`), `px_user_cache_time` (minutes), `token_retention_days`, `apiClient.baseUrl` / `timeout` / `connectTimeout` / `retries` / `retryDelay`, `scout.product_code`.
- Env keys: `PX_USER_API_URL`, `PX_USER_TENANT`, `PX_USER_DOMAIN`, `PX_USER_M2M`, `PX_USER_STAGE`, `PX_USER_CACHE_TIME`, `PX_USER_SCOUT_PRODUCT_CODE`. Never commit or log M2M credentials or tokens.
