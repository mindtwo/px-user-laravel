<?php

namespace mindtwo\PxUserLaravel;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use mindtwo\PxUserLaravel\Contracts\PxUser as ContractsPxUser;
use mindtwo\PxUserLaravel\DataTransfer\PxUserData;
use mindtwo\PxUserLaravel\DataTransfer\PxUserDataWithPermissions;
use mindtwo\PxUserLaravel\Events\PxUserLoginEvent;
use mindtwo\PxUserLaravel\Http\Client\PxUserClient;
use mindtwo\TwoTility\ExternalApiTokens\ExternalApiTokens;
use RuntimeException;

class PxUser
{
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
     * Login a user with PX User access token.
     *
     * @param  array  $tokenData  The token data array
     * @param  string|null  $domain  Optional domain code (defaults to config)
     * @param  string|null  $tenant  Optional tenant code (defaults to config)
     * @return (Model&ContractsPxUser)|false Returns false if no user model is configured or validation fails
     */
    public function login(array $tokenData): (Model&ContractsPxUser)|false
    {
        [$userData, $user] = $this->resolveByToken($tokenData);

        if ($user === false || ! $user instanceof Authenticatable) {
            return false;
        }

        // Store the access token in the repository
        $tokenRepository = resolve(ExternalApiTokens::class)->repository('px-user');
        $tokenRepository->save($user, $tokenData);

        // Authenticate the user
        auth()->login($user);

        event(new PxUserLoginEvent($user, $userData, $user->wasRecentlyCreated));

        return $user;
    }

    public function refresh(string $refreshToken): array
    {
        $tokenRepository = resolve(ExternalApiTokens::class)->repository('px-user');
        $result = $tokenRepository->refresh($refreshToken);

        throw_if(! $result, new RuntimeException('Could not refresh tokens'));

        $user = auth()->user();

        return $tokenRepository->current($user);
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

        $userData = $client->getUserWithPermissions();

        // Retrieve or create user model
        return [$userData, $this->retrieve($userData)];
    }
}
