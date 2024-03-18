<?php

namespace mindtwo\PxUserLaravel\Driver\Contracts;

interface ExpirationHelper
{
    /**
     * Check if access token is expired.
     */
    public function accessTokenExpired(): bool;

    /**
     * Check if tokens can be refreshed.
     */
    public function canRefresh(): bool;
}
