<?php

namespace mindtwo\PxUserLaravel\Actions;

use mindtwo\PxUserLaravel\Facades\AccessTokenHelper;
use mindtwo\PxUserLaravel\Services\CheckUserTokenService;
use mindtwo\PxUserLaravel\Http\PxUserClient;

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
        $checkUserTokenService = app()->make(CheckUserTokenService::class);
        // if auth token is expired try to get a new one
        if (!$checkUserTokenService->check()) {
            return null;
        }

        // fetch with session token
        $accessToken = AccessTokenHelper::get('access_token');

        return $this->pxUserClient->getUserData($accessToken);
    }
}
