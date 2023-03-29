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
        // if auth token is expired try to get a new one
        if (!(new PxUserTokenRefreshAction($this->pxUserClient))->execute()) {
            return null;
        }

        // fetch with session token
        $accessToken = SessionHelper::get('access_token');

        return $this->pxUserClient->getUserData($accessToken);
    }
}
