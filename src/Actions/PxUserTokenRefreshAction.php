<?php

namespace mindtwo\PxUserLaravel\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use mindtwo\PxUserLaravel\Events\PxUserTokenRefreshEvent;
use mindtwo\PxUserLaravel\Helper\SessionHelper;
use mindtwo\PxUserLaravel\Services\PxUserClient;

class PxUserTokenRefreshAction
{
    public function __construct(
        protected PxUserClient $pxUserClient,
    ) {
    }

    /**
     * Undocumented function
     *
     * @return bool
     *
     * @throws Throwable
     */
    public function execute(): bool
    {
        if ($this->tokensExpired()) {
            return false;
        }

        if (!$this->needsRefresh()) {
            return true;
        }

        $refresh_token = SessionHelper::get('px_user_refresh_token');

        try {
            $refreshed = $this->pxUserClient->refreshToken($refresh_token);
        } catch (\Throwable $th) {
            return false;
        }

        // put new tokens into session
        SessionHelper::saveTokenData($refreshed);

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
        $token_expired = Carbon::now()->gt(SessionHelper::get('access_token_expiration_utc'));
        $refresh_expired = Carbon::now()->gt(SessionHelper::get('refresh_token_expiration_utc'));

        return $token_expired && $refresh_expired;
    }

    private function needsRefresh(): bool
    {
        SessionHelper::get('access_token_expiration_utc');
        $token_expired = Carbon::now()->gt(SessionHelper::get('access_token_expiration_utc'));
        $refresh_expired = Carbon::now()->gt(SessionHelper::get('refresh_token_expiration_utc'));

        return $token_expired && !$refresh_expired;
    }
}
