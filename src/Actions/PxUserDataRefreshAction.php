<?php

namespace mindtwo\PxUserLaravel\Actions;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use mindtwo\PxUserLaravel\Helper\SessionHelper;
use mindtwo\PxUserLaravel\Services\PxUserClient;

class PxUserDataRefreshAction
{
    public function __construct(
        protected PxUserClient $pxUserClient,
    ) {
    }

    public function execute(Request $request): ?array
    {
        // if both token are expired return null
        if ($this->tokensExpired($request)) {
            return null;
        }

        // if auth token is expired try to get a new one
        if ($this->needsRefresh($request)) {
            $refresh_token = SessionHelper::get('px_user_refresh_token');
            $refreshed = $this->pxUserClient->refreshToken($refresh_token);

            // put new tokens into session
            SessionHelper::saveTokenData($request, $refreshed);
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
    private function tokensExpired(Request $request): bool
    {
        // TODO configure
        if ($request->is('api/*')) {
            return SessionHelper::get('refresh_token') === null;
        }

        $token_expired = Carbon::now()->gt(SessionHelper::get('access_token_expiration_utc'));
        $refresh_expired = Carbon::now()->gt(SessionHelper::get('refresh_token_expiration_utc'));

        return $token_expired && $refresh_expired;
    }

    private function needsRefresh(Request $request): bool
    {
        // TODO configure
        if ($request->is('api/*')) {
            return SessionHelper::get('access_token') === null;
        }

        SessionHelper::get('access_token_expiration_utc');
        $token_expired = Carbon::now()->gt(SessionHelper::get('access_token_expiration_utc'));
        $refresh_expired = Carbon::now()->gt(SessionHelper::get('refresh_token_expiration_utc'));

        return $token_expired && ! $refresh_expired;
    }
}
