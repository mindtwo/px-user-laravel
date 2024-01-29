<?php

namespace mindtwo\PxUserLaravel\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use mindtwo\PxUserLaravel\Cache\UserDataCache;
use mindtwo\PxUserLaravel\Events\PxUserLoginEvent;
use mindtwo\PxUserLaravel\Facades\AccessTokenHelper;
use mindtwo\PxUserLaravel\Http\PxUserClient;

class PxUserLoginAction
{
    public function __construct(
        protected PxUserClient $pxUserClient,
    ) {
    }

    /**
     * Use token data received from login widget to login user in backend.
     *
     * @param  ?array  $tokenData
     *
     * @throws Exception
     */
    public function execute(?array $tokenData, bool $withPermissions = true): bool
    {
        if (! $this->validateTokenData($tokenData)) {
            return false;
        }

        try {
            $userRequest = $this->pxUserClient->getUserData($tokenData['access_token'], $withPermissions);
        } catch (\Throwable $e) {
            throw new \Exception('No user found.', 0, $e);
        }

        $retrieveUserAction = config('px-user.retrieve_user_action');
        $user = (new $retrieveUserAction)($userRequest);

        if (! $user) {
            return false;
        }

        // save the retrieved user data to cache
        UserDataCache::initialize($userRequest);

        // login user and save token data to cache
        Auth::login($user);
        AccessTokenHelper::saveTokenData($tokenData);

        PxUserLoginEvent::dispatch($user, $userRequest, $tokenData['access_token']);

        return true;
    }

    private function validateTokenData(?array $tokenData): bool
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
