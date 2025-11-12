<?php

namespace mindtwo\PxUserLaravel\Driver\Session;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use mindtwo\PxUserLaravel\Driver\Concerns\SimpleSessionDriver;
use mindtwo\PxUserLaravel\Driver\Contracts\AccessTokenHelper;
use mindtwo\PxUserLaravel\Driver\Contracts\ExpirationHelper;
use mindtwo\PxUserLaravel\Driver\Contracts\SessionDriver;
use mindtwo\PxUserLaravel\Driver\Session\AccessTokenHelper as SessionAccessTokenHelper;
use mindtwo\PxUserLaravel\Driver\Session\ExpirationHelper as SessionExpirationHelper;
use mindtwo\PxUserLaravel\Http\Client\PxUserClient;

class WebSessionDriver implements SessionDriver
{
    use SimpleSessionDriver;

    public function loginUser(Authenticatable $user): ?self
    {
        Auth::login($user);

        return $this;
    }

    public function newAccessTokenHelper(Authenticatable $user): AccessTokenHelper
    {
        return new SessionAccessTokenHelper;
    }

    public function getAccessTokenHelper(): ?AccessTokenHelper
    {
        return new SessionAccessTokenHelper;
    }

    public function getExpirationHelper(): ?ExpirationHelper
    {
        return new SessionExpirationHelper;
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
     */
    public function getDomain(): string
    {
        return config('px-user.domain');
    }

    /**
     * Return if the current session is valid.
     */
    public function validate(): bool
    {
        if (! $user = $this->user()) {
            return false;
        }

        $expirationHelper = $this->getExpirationHelper();

        // if the access token is not expired, the session is valid
        if (! $expirationHelper?->accessTokenExpired()) {
            return true;
        }

        // if the access token is expired and the refresh token is not expired, the session is valid
        if (! $expirationHelper->canRefresh()) {
            return false;
        }

        // try to refresh the session
        if ($this->refresh($user)) {
            return true;
        }

        return false;
    }

    /**
     * Refresh the current session.
     */
    public function refresh(Authenticatable $authenticatable, ?string $refreshToken = null): null|bool|array
    {
        $accessTokenHelper = $this->getAccessTokenHelper();
        if (! $accessTokenHelper) {
            return false;
        }

        $refreshToken = $refreshToken ?? $accessTokenHelper->get('refresh_token');

        // try to get new token data
        $tokenData = $this->getNewRefreshToken($refreshToken);
        if (! $tokenData) {
            return false;
        }

        // save new token data
        $accessTokenHelper->saveTokenData($tokenData);

        return $tokenData;
    }

    /**
     * Refresh token from PX-User API.
     */
    private function getNewRefreshToken(string $refreshToken): ?array
    {
        /** @var PxUserClient $pxClient */
        $pxClient = app()->make('px-user-client', [
            'tenantCode' => $this->getTenant(),
            'domainCode' => $this->getDomain(),
        ]);

        $response = $pxClient->get('refresh-tokens', [
            'headers' => [
                'Authorization' => "Bearer $refreshToken",
            ],
        ]);
        // Check if status is 200
        if ($response->status() !== 200) {
            return null;
        }

        $responseData = $response->json('response');

        return [
            'access_token' => $responseData['access_token'],
            'access_token_expiration_utc' => $responseData['access_token_expiration_utc'],
            'refresh_token' => $responseData['refresh_token'],
            'refresh_token_expiration_utc' => $responseData['refresh_token_expiration_utc'],
        ];
    }
}
