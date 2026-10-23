<?php

namespace mindtwo\PxUserLaravel;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use mindtwo\PxUserLaravel\Contracts\PxUser as ContractsPxUser;
use mindtwo\PxUserLaravel\DataTransfer\PxUserData;
use mindtwo\PxUserLaravel\DataTransfer\PxUserDataWithPermissions;
use mindtwo\PxUserLaravel\Events\PxUserLoginEvent;
use mindtwo\PxUserLaravel\Events\PxUserTokenRefreshEvent;
use mindtwo\PxUserLaravel\Http\Client\PxUserClient;
use mindtwo\PxUserLaravel\Http\Client\PxUserOidcClient;
use mindtwo\PxUserLaravel\Services\PxUserTokens;
use RuntimeException;
use Throwable;

class PxUser
{
    public function __construct(
        private readonly PxUserClient $client,
        private readonly PxUserOidcClient $oidcClient,
        private readonly PxUserTokens $pxUserTokens,
    ) {}

    /**
     * Retrieve or create a user model from PxUserData.
     *
     * @return (Model&ContractsPxUser)|false Returns false if no user model is configured
     */
    public function retrieve(PxUserData|PxUserDataWithPermissions $userData): (Model&ContractsPxUser)|false
    {
        $userModel = config('px-user.user_model');

        if ($userModel === null) {
            return false;
        }

        $pxUserIdKey = config('px-user.px_user_id', 'px_user_id');

        /** @var Model $user */
        $user = $userModel::firstOrCreate([
            $pxUserIdKey => $userData->id,
        ]);

        throw_if(! $user instanceof ContractsPxUser, new RuntimeException('Retrieved authenticatable does not implement PxUser contract.'));

        return $user;
    }

    /**
     * Find a user model by PX User ID.
     */
    public function find(string $pxUserId): ?Authenticatable
    {
        $userModel = config('px-user.user_model');

        if ($userModel === null) {
            return null;
        }

        $pxUserIdKey = config('px-user.px_user_id', 'px_user_id');

        return $userModel::where($pxUserIdKey, $pxUserId)->first();
    }

    /**
     * Validate token data array.
     *
     * @param  array  $tokenData  The token data to validate
     * @return bool Returns true if token data is valid
     */
    public function validateToken(array $tokenData): bool
    {
        $validator = Validator::make($tokenData, [
            'access_token' => 'required|string',
            'access_token_expiration_utc' => 'required|string',
            'refresh_token' => 'sometimes|string',
            'refresh_token_expiration_utc' => 'sometimes|string',
        ]);

        return ! $validator->fails();
    }

    /**
     * Login user over oidc.
     *
     * @return (Model&ContractsPxUser)|false Returns false if no user model is configured or validation fails
     */
    public function oidcLogin(string $code, string $codeVerifier): (Model&ContractsPxUser)|false
    {
        // TOKEN Data from oidc exchange
        $tokenData = $this->oidcClient->exchangeToken($code, $codeVerifier);

        // Transform expires_in to expiration_utc format expected by login
        $tokenData['access_token_expiration_utc'] = Carbon::now()->addSeconds($tokenData['expires_in'])->toIso8601String();

        if (isset($tokenData['refresh_token_expires_in'])) {
            $tokenData['refresh_token_expiration_utc'] = Carbon::now()->addSeconds($tokenData['refresh_token_expires_in'])->toIso8601String();
        }

        return $this->login($tokenData);
    }

    /**
     * Login a user with PX User access token.
     *
     * @param  array  $tokenData  The token data array
     * @return (Model&ContractsPxUser)|false Returns false if no user model is configured or validation fails
     */
    public function login(array $tokenData): (Model&ContractsPxUser)|false
    {
        [$userData, $user] = $this->resolveByToken($tokenData);

        if ($user === false || ! $user instanceof Authenticatable) {
            return false;
        }

        // Store the access token in the repository
        $this->pxUserTokens->save($user, $tokenData);

        // Authenticate the user
        auth()->login($user);

        event(new PxUserLoginEvent($user, $userData, $user->wasRecentlyCreated));

        return $user;
    }

    public function logout(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        $response = $this->client->logout();
        $this->pxUserTokens->invalidate($user);

        return $response;
    }

    public function refresh(string $refreshToken): array
    {
        // Refresh tokens
        $newTokens = $this->client->refreshTokens($refreshToken);

        // Get user by token
        /** @var Authenticatable $user */
        [$userData, $user] = $this->resolveByToken($newTokens);

        // Save the tokens
        $this->pxUserTokens->save($user, $newTokens);

        auth()->login($user);

        return $this->pxUserTokens->current($user);
    }

    /**
     * Refresh the user's PX tokens when they expire within the refresh leeway.
     *
     * Returns whether the user holds a valid token afterwards. Does not log the user in.
     */
    public function refreshSession(Authenticatable $user): bool
    {
        if ($this->hasFreshToken($user)) {
            return true;
        }

        $lockKey = 'px-user:refresh:'.md5(get_class($user)).':'.$user->getAuthIdentifier();

        try {
            $refreshed = Cache::lock($lockKey, $this->refreshLockSeconds())
                ->block(10, fn () => $this->refreshUnlessFresh($user));
        } catch (LockTimeoutException) {
            $refreshed = false;
        }

        return $refreshed || app(PxUserTokens::class)->isCurrentTokenValid($user);
    }

    /**
     * Get how long a refresh may hold its lock: the client's slowest call plus a buffer.
     */
    protected function refreshLockSeconds(): int
    {
        $timeout = (int) config('px-user.apiClient.timeout', 30);
        $attempts = max(1, (int) config('px-user.apiClient.retries', 3));

        return $timeout * $attempts + 30;
    }

    /**
     * Refresh the user's PX tokens unless another request already did.
     */
    protected function refreshUnlessFresh(Authenticatable $user): bool
    {
        if ($this->hasFreshToken($user)) {
            return true;
        }

        // A new instance each time, since PxUserTokens keeps the rows it loaded.
        $tokens = app(PxUserTokens::class);

        try {
            $refreshToken = $tokens->refreshToken($user);
            $refreshValidUntil = $tokens->refreshTokenValidUntil($user);

            if (empty($refreshToken) || $refreshValidUntil?->isPast()) {
                return false;
            }

            $newTokens = app(PxUserClient::class)->refreshTokens($refreshToken);

            if (! $this->validateToken($newTokens)) {
                return false;
            }

            $tokens->save($user, $newTokens);
        } catch (Throwable $e) {
            Log::debug('PX-User: '.$e->getMessage());

            return false;
        }

        event(new PxUserTokenRefreshEvent($user, $newTokens['access_token']));

        return true;
    }

    /**
     * Check whether the user's token stays valid beyond the refresh leeway.
     */
    protected function hasFreshToken(Authenticatable $user): bool
    {
        try {
            $expiresAt = app(PxUserTokens::class)->expiresAt($user);
        } catch (RuntimeException) {
            return false;
        }

        return $expiresAt === null
            || $expiresAt->isAfter(now()->addSeconds((int) config('px-user.refresh_leeway', 60)));
    }

    /**
     * Resolve user by token without logging in.
     *
     * @param  array  $tokenData  The token data array
     * @return array{0: PxUserDataWithPermissions, 1: (Model&ContractsPxUser)|false}
     */
    public function resolveByToken(array $tokenData): array
    {
        // Validate token data
        if (! $this->validateToken($tokenData)) {
            throw new RuntimeException('Invalid token data provided.');
        }

        // Fetch user data from PX User API
        /** @var PxUserClient $client */
        $client = app(PxUserClient::class);
        $client->setAccessToken($tokenData['access_token']);

        $userData = $client->getUserWithPermissions(
            (bool) config('px-user.extended_products_on_login', false),
        );

        // Retrieve or create user model
        return [$userData, $this->retrieve($userData)];
    }
}
