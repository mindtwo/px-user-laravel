<?php

namespace mindtwo\PxUserLaravel\Actions;

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
}
