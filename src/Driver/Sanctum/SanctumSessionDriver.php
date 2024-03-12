<?php

namespace mindtwo\PxUserLaravel\Driver\Sanctum;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use mindtwo\PxUserLaravel\Driver\Concerns\SimpleSessionDriver;
use mindtwo\PxUserLaravel\Driver\Contracts\SessionDriver;
use mindtwo\PxUserLaravel\Http\Client\PxClient;
use mindtwo\PxUserLaravel\Traits\HasRefreshableApiTokens;

class SanctumSessionDriver implements SessionDriver
{
    use SimpleSessionDriver;

    private ?AccessTokenHelper $accessTokenHelper = null;

    public function newAccessTokenHelper(Authenticatable $authenticatable): AccessTokenHelper
    {
        $this->accessTokenHelper = new AccessTokenHelper($authenticatable);

        return $this->accessTokenHelper;
    }

    public function getAccessTokenHelper(): ?AccessTokenHelper
    {
        if ($this->accessTokenHelper) {
            return $this->accessTokenHelper;
        }

        if (! $this->user()) {
            return null;
        }

        $this->accessTokenHelper = new AccessTokenHelper($this->user());

        return $this->accessTokenHelper;
    }

    public function getExpirationHelper(): ?ExpirationHelper
    {
        return new ExpirationHelper($this->getAccessTokenHelper());
    }

    /**
     * Get the tenant of the current session.
     */
    public function getTenant(): string
    {
        return $this->user()->tenant_code ?? config('px-user.tenant');
    }

    /**
     * Return the domain of the current session.
     */
    public function getDomain(): string
    {
        return $this->user()->domain_code ?? config('px-user.domain');
    }

    /**
     * Return if the current session is valid.
     */
    public function valid(): bool
    {
        return true;
    }

    /**
     * Refresh the current session.
     */
    public function refresh(Authenticatable $authenticatable, ?string $refreshToken = null): null|bool|array
    {
        if (! in_array(HasRefreshableApiTokens::class, class_uses_recursive(get_class($authenticatable)))) {
            return null;
        }

        $this->setUser($authenticatable);

        $accessTokenHelper = $this->getAccessTokenHelper();
        $expirationHelper = $this->getExpirationHelper();

        $refreshToken = $refreshToken ?? $accessTokenHelper->get('refresh_token');
        $currentPlainTextToken = str_replace('Bearer ', '', request()->header('Authorization'));
        $isAboutToExpire = $expirationHelper->accessTokenExpired() || $expirationHelper->accessTokenExpiringSoon();

        if ($currentPlainTextToken && ! $isAboutToExpire) {
            Log::info('Token is still valid', [
                'currentPlainTextToken' => $currentPlainTextToken,
                'isAboutToExpire' => $isAboutToExpire,
            ]);

            return [
                'access_token' => $currentPlainTextToken,
                'expires_at' => $accessTokenHelper->get('access_token_expiration_utc'),
                'refresh_token' => $refreshToken,
            ];
        }

        // check if we can refresh the token
        if ($expirationHelper->accessTokenExpired() && ! $expirationHelper->canRefresh()) {
            return false;
        }

        // check if we need to refresh the token
        if ($isAboutToExpire) {
            $newTokenData = $this->getNewRefreshToken($refreshToken);
        }

        $refreshToken = isset($newTokenData) ? $newTokenData['refresh_token'] : $refreshToken;
        $newExpiresAt = isset($newTokenData) ? Carbon::parse($newTokenData['access_token_expiration_utc']) : null;
        $refreshedToken = $authenticatable->refreshAccessToken($refreshToken, $newExpiresAt);

        $accessToken = $refreshedToken->plainTextToken;
        $expires_at = $refreshedToken->accessToken->expires_at;

        return [
            'access_token' => $accessToken,
            'expires_at' => $expires_at,
            'refresh_token' => $refreshToken,
        ];
    }

    /**
     * Refresh token from PX-User API.
     */
    private function getNewRefreshToken(string $refreshToken): array
    {
        $pxClient = app()->make(PxClient::class, [
            'tenantCode' => $this->getTenant(),
            'domainCode' => $this->getDomain(),
        ]);

        $response = $pxClient->get('user/refresh-token', [
            'headers' => [
                'Authorization' => "Bearer $refreshToken",
            ],
        ]);
        // Check if status is 200
        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Error Processing Request', 1);
        }

        $body = $response->getBody();
        $responseData = optional(json_decode((string) $body))->response;

        return [
            'access_token' => $responseData->access_token,
            'access_token_expiration_utc' => $responseData->access_token_expiration_utc,
            'refresh_token' => $responseData->refresh_token,
            'refresh_token_expiration_utc' => $responseData->refresh_token_expiration_utc,
        ];
    }
}