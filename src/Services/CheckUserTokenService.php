<?php

namespace mindtwo\PxUserLaravel\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use mindtwo\PxUserLaravel\Events\PxUserTokenRefreshEvent;
use mindtwo\PxUserLaravel\Facades\AccessTokenHelper;
use mindtwo\PxUserLaravel\Http\PxAdminClient;

class CheckUserTokenService
{
    public function __construct(
    ) {
    }

    /**
     * Undocumented function
     *
     * @return bool
     *
     * @throws Throwable
     */
    public function check(): bool
    {
        // $token_expired && $refresh_expired
        $accessTokenExpired = AccessTokenHelper::accessTokenExpired();

        if (! $accessTokenExpired) {
            return true;
        }

        $canRefresh = AccessTokenHelper::canRefresh();
        if (! $canRefresh) {
            return false;
        }

        $refresh_token = AccessTokenHelper::get('refresh_token');

        try {
            $pxAdminClient = App::make(PxAdminClient::class);

            $refreshed = $pxAdminClient->refreshToken($refresh_token);
        } catch (\Throwable $th) {
            return false;
        }

        if ($refreshed === null) {
            return false;
        }

        // put new tokens into session
        AccessTokenHelper::saveTokenData($refreshed);

        PxUserTokenRefreshEvent::dispatch(Auth::user(), $refreshed['access_token']);

        return true;
    }
}
