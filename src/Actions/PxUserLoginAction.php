<?php

namespace mindtwo\PxUserLaravel\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use mindtwo\PxUserLaravel\Events\PxUserLoginEvent;
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
    public function execute(?array $tokenData): bool
    {
        if (! $this->validateTokenData($tokenData)) {
            return false;
        }

        $this->saveTokenToSession($tokenData);

        try {
            $userRequest = $this->pxUserClient->getUserData($tokenData['access_token']);

            if (null === config('px-user.user_model')) {
                return false;
            }

            $user = config('px-user.user_model')::firstOrCreate([
                'px_user_id' => $userRequest['id'],
            ]);

            Auth::login($user);

            PxUserLoginEvent::dispatch($user, $userRequest);

            return true;
        } catch (\Throwable $e) {
            throw new \Exception('No user found.', 0, $e);
        }
    }

    private function saveTokenToSession(array $tokenData): void
    {
        Session::put('px_user_token', $tokenData['access_token']);
        Session::put('px_user_token_expiration_utc', $tokenData['access_token_expiration_utc']);
        Session::put('px_user_refresh_token', $tokenData['refresh_token']);
        Session::put('px_user_refresh_token_expiration_utc', $tokenData['refresh_token_expiration_utc']);
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
