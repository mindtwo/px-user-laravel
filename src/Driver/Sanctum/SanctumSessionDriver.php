<?php

namespace mindtwo\PxUserLaravel\Driver\Sanctum;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
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
    public function validate(): bool
    {
        $expirationHelper = $this->getExpirationHelper();
        $accessTokenHelper = $this->getAccessTokenHelper();

        // check if we can refresh the token
        if ($expirationHelper->accessTokenExpired() && ! $expirationHelper->canRefresh()) {
            return false;
        }

        // check if we need to refresh the token
        if ($expirationHelper->accessTokenExpired()) {
            try {
                // Refresh the token
                $this->refreshAccessToken($accessTokenHelper->get('refresh_token'), true);
            } catch (\Throwable $th) {
                return false;
            }
        }

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

        // check if we can return the current token and don't need to refresh
        if ($currentPlainTextToken && ! $expirationHelper->accessTokenExpired()) {
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

        // refresh the token
        /** @var \Laravel\Sanctum\NewAccessToken $refreshedToken */
        $refreshedToken = $this->refreshAccessToken($refreshToken);

        return [
            'access_token' => $refreshedToken->plainTextToken,
            'refresh_token' => $refreshedToken->accessToken->refresh_token,
            'expires_at' => $refreshedToken->accessToken->expires_at,
        ];
    }

    /**
     * Refresh the current user's access token using the given refresh token.
     * The refresh token is used to obtain a new valid access token from the PX-User API.
     *
     * @param string $refreshToken
     * @return ?\Laravel\Sanctum\NewAccessToken - retuns null if onlyUpdate is true
     */
    private function refreshAccessToken(string $refreshToken, bool $onlyUpdate = false): ?\Laravel\Sanctum\NewAccessToken
    {
        // Get new token from PX-User API
        $newTokenData = $this->getNewRefreshToken($refreshToken);

        // Save new token data in the "session"
        $this->getAccessTokenHelper()->saveTokenData($newTokenData);

        // Update the current token for the user
        $newExpiresAt = Carbon::parse($newTokenData['access_token_expiration_utc']);
        $newRefreshToken = $newTokenData['refresh_token'];

        if ($onlyUpdate) {
            $this->user()->updateCurrentAccessTokenExpiration($refreshToken, $newRefreshToken, $newExpiresAt);
            return null;
        }

        $refreshedToken = $this->user()->refreshAccessToken($refreshToken, $newRefreshToken, $newExpiresAt);

        return $refreshedToken;
    }

    /**
     * Refresh token from PX-User API.
     */
    private function getNewRefreshToken(string $refreshToken): array
    {
        /** @var PxClient $pxClient */
        $pxClient = app()->make(PxClient::class, [
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
            throw new \Exception('Error Processing Request', 1);
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
