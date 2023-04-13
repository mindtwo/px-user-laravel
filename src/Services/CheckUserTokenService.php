<?php

namespace mindtwo\PxUserLaravel\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
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

        // put new tokens into session
        AccessTokenHelper::saveTokenData($refreshed);

        PxUserTokenRefreshEvent::dispatch(Auth::user(), $refreshed['access_token']);

        return true;
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

    private function needsRefresh(): bool
    {
        AccessTokenHelper::get('access_token_expiration_utc');
        $token_expired = Carbon::now()->gt(AccessTokenHelper::get('access_token_expiration_utc'));
        $refresh_expired = Carbon::now()->gt(AccessTokenHelper::get('refresh_token_expiration_utc'));

        return $token_expired && !$refresh_expired;
    }
}
