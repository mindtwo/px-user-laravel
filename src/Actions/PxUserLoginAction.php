<?php

namespace mindtwo\PxUserLaravel\Actions;

use Illuminate\Support\Facades\Auth;
use mindtwo\PxUserLaravel\Events\PxUserLoginEvent;
use mindtwo\PxUserLaravel\Helper\AccessTokenHelper;
use mindtwo\PxUserLaravel\Services\PxUserClient;

class PxUserLoginAction
{
    public function __construct(
        protected PxUserClient $pxUserClient,
    ) {
    }

    /**
     * Use token data received from login widget to login user in backend
     *
     * @param  ?array  $tokenData
     * @param  bool  $withPermissions
     * @return bool
     *
     * @throws Exception
     */
    public function execute(?array $tokenData, bool $withPermissions=true): bool
    {
        if (! $this->validateTokenData($tokenData)) {
            return false;
        }

        try {
            $userRequest = $this->pxUserClient->getUserData($tokenData['access_token'], $withPermissions);

            $retrieveUserAction = config('px-user.retrieve_user_action');

            $user = (new $retrieveUserAction)($userRequest);

            if (!$user) {
                return false;
            }

            Auth::login($user);

            // save token data to session or cache
            AccessTokenHelper::saveTokenData($tokenData);

            PxUserLoginEvent::dispatch($user, $userRequest, $tokenData['access_token']);

            return true;
        } catch (\Throwable $e) {
            throw new \Exception('No user found.', 0, $e);
        }
    }

    private function validateTokenData(?array $tokenData): bool
    {
        if (empty($tokenData)) {
            return false;
        }

        $requiredKeys = ['access_token', 'access_token_expiration_utc', 'refresh_token', 'refresh_token_expiration_utc'];

        return ! array_diff_key(array_flip($requiredKeys), $tokenData);
    }
}
