<?php

namespace mindtwo\PxUserLaravel\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use mindtwo\PxUserLaravel\Services\PxUserClient;

class PxUserDataRefreshAction
{
    public function __construct(
        protected PxUserClient $pxUserClient,
    ) {
    }

    public function execute(): ?array
    {
        // if both token are expired return null
        if ($this->tokensExpired()) {
            return null;
        }

        // if auth token is expired try to get a new one
        if ($this->needsRefresh()) {
            $refresh_token = Session::get('px_user_refresh_token');
            $refreshed = $this->pxUserClient->refreshToken($refresh_token);

            // put new tokens into session
            Session::put('px_user_token', $refreshed['access_token']);
            Session::put('px_user_token_expiration_utc', $refreshed['access_token_expiration_utc']);
            Session::put('px_user_refresh_token', $refreshed['refresh_token']);
            Session::put('px_user_refresh_token_expiration_utc', $refreshed['refresh_token_expiration_utc']);
        }

        // fetch with session token
        $accessToken = Session::get('px_user_token');

        return $this->pxUserClient->getUserData($accessToken);
    }

    /**
     * Check if both tokens are expired
     *
     * @return bool
     */
    private function tokensExpired(): bool
    {
        $token_expired = Carbon::now()->gt(Session::get('px_user_token_expiration_utc'));
        $refresh_expired = Carbon::now()->gt(Session::get('px_user_refresh_token_expiration_utc'));

        return $token_expired && $refresh_expired;
    }

    private function needsRefresh(): bool
    {
        $token_expired = Carbon::now()->gt(Session::get('px_user_token_expiration_utc'));
        $refresh_expired = Carbon::now()->gt(Session::get('px_user_refresh_token_expiration_utc'));

        return $token_expired && ! $refresh_expired;
    }
}
