<?php

namespace mindtwo\PxUserLaravel\Driver\Session;

use Illuminate\Contracts\Auth\Authenticatable;
use mindtwo\PxUserLaravel\Driver\Concerns\SimpleSessionDriver;
use mindtwo\PxUserLaravel\Driver\Contracts\AccessTokenHelper;
use mindtwo\PxUserLaravel\Driver\Contracts\ExpirationHelper;
use mindtwo\PxUserLaravel\Driver\Contracts\SessionDriver;
use mindtwo\PxUserLaravel\Driver\Session\AccessTokenHelper as SessionAccessTokenHelper;
use mindtwo\PxUserLaravel\Driver\Session\ExpirationHelper as SessionExpirationHelper;

class WebSessionDriver implements SessionDriver
{

    use SimpleSessionDriver;

    public function newAccessTokenHelper(Authenticatable $user): AccessTokenHelper
    {
        return new SessionAccessTokenHelper();
    }

    public function getAccessTokenHelper(): ?AccessTokenHelper
    {
        return new SessionAccessTokenHelper();
    }

    public function getExpirationHelper(): ?ExpirationHelper
    {
        return new SessionExpirationHelper();
    }

    /**
     * Get the tenant of the current session.
     */
    public function getTenant(): string
    {
        return config('px-user.tenant');
    }

    /**
     * Return the domain of the current session.
     *
     * @return string
     */
    public function getDomain(): string
    {
        return config('px-user.domain');
    }

    /**
     * Return if the current session is valid.
     */
    public function valid(): bool
    {
        $expirationHelper = $this->getExpirationHelper();

        return true;
        // return $expirationHelper->accessTokenExpired() && ! $expirationHelper->canRefresh();
    }

    /**
     * Refresh the current session.
     */
    public function refresh(Authenticatable $authenticatable, ?string $refreshToken = null): null|bool|array
    {
        return null;
    }

}
