<?php

namespace mindtwo\PxUserLaravel\Actions;

use mindtwo\PxUserLaravel\Helper\AccessTokenHelper;
use mindtwo\PxUserLaravel\Services\CheckUserTokenService;
use mindtwo\PxUserLaravel\Services\PxUserClient;

class PxUserDataRefreshAction
{
    public function __construct(
        protected PxUserClient $pxUserClient,
        protected CheckUserTokenService $checkUserTokenService,
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
        // if auth token is expired try to get a new one
        if (!$this->checkUserTokenService->check()) {
            return null;
        }

        // fetch with session token
        $accessToken = AccessTokenHelper::get('access_token');

        return $this->pxUserClient->getUserData($accessToken);
    }
}
