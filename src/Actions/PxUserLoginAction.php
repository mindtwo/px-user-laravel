<?php

namespace mindtwo\PxUserLaravel\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use mindtwo\PxUserLaravel\Events\PxUserLoginEvent;
use mindtwo\PxUserLaravel\Helper\SessionHelper;
use mindtwo\PxUserLaravel\Facades\UserDataCache;
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
     * @param  array  $tokenData
     * @return bool
     *
     * @throws Exception
     */
    public function execute(Request $request, ?array $tokenData): bool
    {
        if (! $this->validateTokenData($tokenData)) {
            return false;
        }

        try {
            $userRequest = $this->pxUserClient->getUserData($tokenData['access_token']);

            if (null === config('px-user.user_model')) {
                return false;
            }

            $user = config('px-user.user_model')::firstOrCreate([
                config('px-user.px_user_id') => $userRequest['id'],
            ]);

            Auth::login($user);

            SessionHelper::saveTokenData($tokenData, $request);

            UserDataCache::saveUserData($userRequest);

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
