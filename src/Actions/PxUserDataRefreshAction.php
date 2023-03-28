<?php

namespace mindtwo\PxUserLaravel\Actions;

use Carbon\Carbon;
use mindtwo\PxUserLaravel\Helper\SessionHelper;
use mindtwo\PxUserLaravel\Services\PxUserClient;

class PxUserDataRefreshAction
{
    public function __construct(
        protected PxUserClient $pxUserClient,
    ) {
    }

    /**
     * Undocumented function
     *
     * @return ?array
     *
     * @throws Throwable
     */
    public function execute(): ?array
    {
        // if both token are expired return null
        if ($this->tokensExpired()) {
            return null;
        }

        // if auth token is expired try to get a new one
        if ($this->needsRefresh() && !(new PxUserTokenRefreshAction($this->pxUserClient))->execute()) {
            return null;
        }

        // fetch with session token
        $accessToken = SessionHelper::get('access_token');

        return $this->pxUserClient->getUserData($accessToken);
    }

    /**
     * Check if both tokens are expired
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

        return $token_expired && ! $refresh_expired;
    }
}
