<?php

namespace mindtwo\PxUserLaravel\Actions;

use Illuminate\Http\Request;
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
     * @param Request $request
     * @return bool
     *
     * @throws Throwable
     */
    public function execute(?Request $request = null): bool
    {
        $refresh_token = SessionHelper::get('px_user_refresh_token');

        try {
            $refreshed = $this->pxUserClient->refreshToken($refresh_token);
        } catch (\Throwable $th) {
            return false;
        }

        // put new tokens into session
        SessionHelper::saveTokenData($refreshed, $request);

        PxUserTokenRefreshEvent::dispatch(Auth::user(), $refreshed['access_token']);

        return true;
    }
}
