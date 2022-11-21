<?php

namespace Mindtwo\PxUserLaravel\Actions;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Mindtwo\PxUserLaravel\Services\PxUserClient;

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

            $user = User::firstOrCreate([
                'px_user_id' => $userRequest['id'],
            ]);

            if (in_array($userRequest['email'], [
                'emde@mindtwo.de',
                'schneider@mindtwo.de',
                'csi@vnr.de',
                'nik@vnr.de',
            ])) {
                $user->role = RoleEnum::Admin;
                $user->save();
            }

            Auth::login($user);

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
