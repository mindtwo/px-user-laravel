<?php

namespace mindtwo\PxUserLaravel\Driver\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use mindtwo\PxUserLaravel\Cache\UserDataCache;
use mindtwo\PxUserLaravel\Data\PxUserPermissionData;
use mindtwo\PxUserLaravel\Events\PxUserLoginEvent;
use mindtwo\PxUserLaravel\Http\Client\PxUserClient;

trait SimpleSessionDriver
{
    protected ?\Illuminate\Contracts\Auth\Authenticatable $user = null;

    public function driver(): self
    {
        return $this;
    }

    abstract public function loginUser(Authenticatable $user): ?self;

    /**
     * Login a user.
     */
    public function login(array $tokenData): ?self
    {
        if (! $this->validateTokenData($tokenData)) {
            return null;
        }

        $pxClient = app()->make('px-user-client');

        try {
            $userRequest = $pxClient->get(PxUserClient::USER_WITH_PERMISSIONS, [
                'headers' => ['Authorization' => 'Bearer '.$tokenData['access_token']],
            ], [
                'withExtendedProducts' => true,
            ]);
        } catch (\Throwable $e) {
            throw new \Exception('No user found.', 0, $e);
        }

        $response = $userRequest->json('response');

        if (! ($response['user'] ?? false)) {
            return null;
        }

        $retrieveUserAction = config('px-user.retrieve_user_action');
        $user = (new $retrieveUserAction)($response['user']);

        if (! $user) {
            return null;
        }

        $this->user = $user;

        $this->getAccessTokenHelper()->saveTokenData($tokenData);

        // save the retrieved user data to cache
        UserDataCache::initialize($response['user']);

        // login the user in the Laravel session
        $this->loginUser($user);

        PxUserLoginEvent::dispatch(
            $user,
            PxUserPermissionData::fromArray($response['user']),
            $tokenData['access_token'],
        );

        return $this;
    }

    /**
     * Logout the current session.
     */
    public function logout(): bool
    {
        try {
            Cache::forget(cache_key('data_cache', [
                'name' => 'user',
                'uuid' => $this->userId(),
            ])->toString());

            $this->getAccessTokenHelper()?->flush();

            // TODO invalidate personal access token here

            // @phpstan-ignore-next-line
            if (method_exists(app('auth'), 'logout')) {
                optional((app('auth')))->logout();
            }
        } catch (\Throwable $th) {
            Log::error($th);

            return false;
        }

        $this->user = null;

        return true;
    }

    public function userId(): null|int|string
    {
        return $this->user ? $this->user->{config('px-user.px_user_id')} : null;
    }

    public function user(): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        return $this->user;
    }

    public function setUser(\Illuminate\Contracts\Auth\Authenticatable $user): void
    {
        $this->user = $user;
    }

    protected function validateTokenData(?array $tokenData): bool
    {
        if (empty($tokenData)) {
            return false;
        }

        $validator = Validator::make($tokenData, [
            'access_token' => 'required|string',
            'access_token_expiration_utc' => 'required|string',
            'refresh_token' => 'sometimes|string',
            'refresh_token_expiration_utc' => 'sometimes|string',
        ]);

        return ! $validator->fails();
    }
}
