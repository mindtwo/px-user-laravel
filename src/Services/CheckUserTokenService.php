<?php

namespace mindtwo\PxUserLaravel\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use mindtwo\PxUserLaravel\Events\PxUserTokenRefreshEvent;
use mindtwo\PxUserLaravel\Helper\AccessTokenHelper;
use mindtwo\PxUserLaravel\Services\PxAdminClient;

class CheckUserTokenService
{
    public function __construct(
        protected PxAdminClient $pxAdminClient,
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
        if ($this->tokensExpired()) {
            return false;
        }

        if (!$this->needsRefresh()) {
            return true;
        }

        $refresh_token = AccessTokenHelper::get('px_user_refresh_token');

        try {
            $refreshed = $this->pxAdminClient->refreshToken($refresh_token);
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

    /**
     * Check if access token is expired.
     *
     * @return boolean
     */
    public function accessTokenExpired(): bool
    {
        return null !== ($time = AccessTokenHelper::get('access_token_expiration_utc')) && Carbon::now()->gt($time);
    }

    /**
     * Check if tokens can be refreshed.
     *
     * @return boolean
     */
    public function canRefresh(): bool
    {
        return AccessTokenHelper::get('refresh_token') !== null && null !== ($time = AccessTokenHelper::get('refresh_token_expiration_utc')) && Carbon::now()->gt($time);
    }

    /**
     * Check if both tokens are expired.
     *
     * @return bool
     */
    private function tokensExpired(): bool
    {
        $token_expired = Carbon::now()->gt(AccessTokenHelper::get('access_token_expiration_utc'));
        $refresh_expired = Carbon::now()->gt(AccessTokenHelper::get('refresh_token_expiration_utc'));

        return $token_expired && $refresh_expired;
    }

    /**
     * Check if access_token is expired but not the refresh_token
     *
     * @return boolean
     */
    private function needsRefresh(): bool
    {
        $token_expired = Carbon::now()->gt(AccessTokenHelper::get('access_token_expiration_utc'));
        $refresh_expired = Carbon::now()->gt(AccessTokenHelper::get('refresh_token_expiration_utc'));

        return $token_expired && !$refresh_expired;
    }
}
