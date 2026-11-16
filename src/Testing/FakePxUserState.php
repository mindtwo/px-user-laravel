<?php

namespace mindtwo\PxUserLaravel\Testing;

use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use mindtwo\PxUserLaravel\Contracts\PxUser as ContractsPxUser;
use mindtwo\PxUserLaravel\Models\PxUserToken;
use Throwable;

/**
 * The PX users, tokens, failures and recorded calls of one faked test.
 *
 * @internal Use FakePxUser instead.
 */
final class FakePxUserState
{
    /**
     * The users PX knows, as API payloads keyed by PX user id.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $users = [];

    /**
     * The ids PX does not know, even when a local user has them.
     *
     * @var array<string, true>
     */
    private array $forgotten = [];

    /**
     * The PX user id each issued access token belongs to.
     *
     * @var array<string, string>
     */
    private array $accessTokens = [];

    /**
     * The PX user id each issued refresh token belongs to.
     *
     * @var array<string, string>
     */
    private array $refreshTokens = [];

    /**
     * The user an unknown access token or an OIDC code resolves to.
     */
    private ?string $nextLoginId = null;

    /**
     * The status code or exception each failing endpoint answers with.
     *
     * @var array<string, int|Throwable>
     */
    private array $failures = [];

    /**
     * The calls made to each endpoint, in order.
     *
     * @var array<string, list<array<string, mixed>>>
     */
    private array $recorded = [];

    private int $issuedTokens = 0;

    /**
     * Add the user to PX, merging the given payload into what PX already knows about them.
     *
     * @param  array<string, mixed>  $payload  Snake_case payload keys
     * @return array<string, mixed>
     */
    public function put(string $pxUserId, array $payload): array
    {
        unset($this->forgotten[$pxUserId]);

        return $this->users[$pxUserId] = PxUserPayload::make([
            ...($this->users[$pxUserId] ?? []),
            ...$payload,
            'id' => $pxUserId,
        ]);
    }

    /**
     * Add a local user to PX, with defaults taken from the model.
     *
     * @param  array<string, mixed>  $payload  Snake_case payload keys
     * @return array<string, mixed>
     */
    public function putUser(ContractsPxUser $user, array $payload = []): array
    {
        $pxUserId = $user->getPxUserId();

        if (! isset($this->users[$pxUserId])) {
            // A model without codes (and none configured) throws a TypeError; the payload defaults apply then
            $payload = [
                ...array_filter([
                    'tenant_code' => rescue(fn () => $user->getPxUserTenantCode(), null, false),
                    'domain_code' => rescue(fn () => $user->getPxUserDomainCode(), null, false),
                ]),
                ...$payload,
            ];
        }

        return $this->put($pxUserId, $payload);
    }

    /**
     * Find a user PX knows; a local user PX was not told about is added with defaults.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $pxUserId): ?array
    {
        if (isset($this->forgotten[$pxUserId])) {
            return null;
        }

        if (isset($this->users[$pxUserId])) {
            return $this->users[$pxUserId];
        }

        $user = self::localUser($pxUserId);

        return $user instanceof ContractsPxUser ? $this->putUser($user) : null;
    }

    /**
     * Find the local user with the PX user id, without the PxUser service, which tests may mock.
     */
    public static function localUser(string $pxUserId): ?ContractsPxUser
    {
        $userModel = config('px-user.user_model');

        if ($userModel === null) {
            return null;
        }

        $user = $userModel::query()->where(config('px-user.px_user_id', 'px_user_id'), $pxUserId)->first();

        return $user instanceof ContractsPxUser ? $user : null;
    }

    /**
     * Find the users whose name, username or email contains every search term.
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $names): array
    {
        $terms = preg_split('/\s+/', Str::lower(trim($names)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($this->users, function (array $user) use ($terms): bool {
            $haystack = Str::lower(implode(' ', [
                $user['firstname'] ?? '', $user['lastname'] ?? '', $user['preferred_username'] ?? '', $user['email'] ?? '',
            ]));

            return $terms !== [] && collect($terms)->every(fn (string $term) => str_contains($haystack, $term));
        }));
    }

    /**
     * Remove the user from PX.
     */
    public function forget(string $pxUserId): void
    {
        unset($this->users[$pxUserId]);
        $this->forgotten[$pxUserId] = true;

        if ($this->nextLoginId === $pxUserId) {
            $this->nextLoginId = null;
        }
    }

    /**
     * Let unknown access tokens and OIDC codes resolve to the user.
     */
    public function setNextLogin(?string $pxUserId): void
    {
        $this->nextLoginId = $pxUserId;
    }

    /**
     * Get the user an access token belongs to.
     *
     * An unknown token resolves to the next login user, or to a new PX user, and is then
     * remembered for that user. Null when the user was removed from PX.
     *
     * @return array<string, mixed>|null
     */
    public function userForAccessToken(string $accessToken): ?array
    {
        $pxUserId = $this->accessTokens[$accessToken] ??= $this->nextLoginId();

        return $this->find($pxUserId);
    }

    /**
     * Get the PX user id a refresh token belongs to.
     */
    public function ownerOfRefreshToken(string $refreshToken): ?string
    {
        if (isset($this->refreshTokens[$refreshToken])) {
            return $this->refreshTokens[$refreshToken];
        }

        // A token the app stored (e.g. from a login request) was issued by PX too
        $user = PxUserToken::query()
            ->latest('id')
            ->get()
            ->first(fn (PxUserToken $token) => $token->token('refresh_token') === $refreshToken)
            ?->authenticatable;

        if (! $user instanceof ContractsPxUser) {
            return null;
        }

        return $this->refreshTokens[$refreshToken] = $user->getPxUserId();
    }

    /**
     * Issue a new token pair for the user, valid for an hour and a week.
     *
     * @return array{access_token: string, access_token_expiration_utc: string, refresh_token: string, refresh_token_expiration_utc: string}
     */
    public function issueTokens(string $pxUserId): array
    {
        $number = ++$this->issuedTokens;
        $accessToken = "fake-access-token-{$number}";
        $refreshToken = "fake-refresh-token-{$number}";

        $this->accessTokens[$accessToken] = $pxUserId;
        $this->refreshTokens[$refreshToken] = $pxUserId;

        return [
            'access_token' => $accessToken,
            'access_token_expiration_utc' => now()->addHour()->startOfSecond()->toIso8601String(),
            'refresh_token' => $refreshToken,
            'refresh_token_expiration_utc' => now()->addWeek()->startOfSecond()->toIso8601String(),
        ];
    }

    /**
     * Get the next login user's id, adding a new PX user when there is none.
     */
    public function nextLoginId(): string
    {
        if ($this->nextLoginId !== null) {
            return $this->nextLoginId;
        }

        $pxUserId = (string) Str::uuid();
        $this->put($pxUserId, []);

        return $pxUserId;
    }

    /**
     * Let the endpoint fail with the status code or exception until it succeeds again.
     */
    public function fail(PxUserEndpoint $endpoint, int|Throwable $with): void
    {
        $this->failures[$endpoint->value] = $with;
    }

    /**
     * Let the endpoint answer normally again.
     */
    public function succeed(PxUserEndpoint $endpoint): void
    {
        unset($this->failures[$endpoint->value]);
    }

    /**
     * Record a call to the endpoint, then throw when it is set to fail.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws Throwable
     */
    public function call(PxUserEndpoint $endpoint, array $data = []): void
    {
        $this->recorded[$endpoint->value][] = $data;

        $failure = $this->failures[$endpoint->value] ?? null;

        if ($failure instanceof Throwable) {
            throw $failure;
        }

        if ($failure !== null) {
            throw self::requestException($failure);
        }
    }

    /**
     * Get the calls made to the endpoint, in order.
     *
     * @return list<array<string, mixed>>
     */
    public function recorded(PxUserEndpoint $endpoint): array
    {
        return $this->recorded[$endpoint->value] ?? [];
    }

    /**
     * Build the exception the HTTP client throws for a PX error response.
     */
    public static function requestException(int $status, string $message = 'Faked PX User error'): RequestException
    {
        return new RequestException(new Response(new PsrResponse(
            $status,
            ['Content-Type' => 'application/json'],
            (string) json_encode(['message' => $message]),
        )));
    }
}
