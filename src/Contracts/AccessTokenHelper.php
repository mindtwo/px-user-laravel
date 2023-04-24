<?php

namespace mindtwo\PxUserLaravel\Contracts;

use Carbon\Carbon;

abstract class AccessTokenHelper
{

    private array $accessTokenKeys = ['access_token', 'access_token_expiration_utc', 'refresh_token', 'refresh_token_expiration_utc'];

    /**
     * Check if access token is expired.
     *
     * @return boolean
     */
    public function accessTokenExpired(): bool
    {
        return null === ($time = $this->get('access_token_expiration_utc')) || Carbon::now()->gt($time);
    }

    /**
     * Check if tokens can be refreshed.
     *
     * @return boolean
     */
    public function canRefresh(): bool
    {
        return $this->get('refresh_token') !== null && null !== ($time = $this->get('refresh_token_expiration_utc')) && Carbon::now()->lt($time);
    }

    /**
     * Save token data either to cache or session
     *
     * @param array $tokenData
     * @return void
     */
    public function saveTokenData(array $tokenData): void
    {
        foreach ($this->accessTokenKeys as $key) {
            if (isset($tokenData[$key])) {
                $this->put($key, $tokenData[$key]);
            }
        }
    }

    /**
     * Get px user token data for current user
     *
     * @return array
     */
    public function values()
    {
        return collect($this->accessTokenKeys)
            ->mapWithKeys(fn ($key) => [$key => $this->get($key)])
            ->toArray();
    }

    /**
     * Remove session data for request
     *
     * @return void
     */
    abstract public function flush();

    /**
     * Put value for passed key
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    abstract public function put(string $key, string $value);

    /**
     * Get value for passed key
     *
     * @param string $key
     * @return mixed
     */
    abstract public function get(string $key): mixed;

    public function prefix(): string
    {
        return config('px-user.session_prefix') ?? 'px_user';
    }

    /**
     * Check if key is in array of
     * keys which are allowed to be handled
     * by this helper.
     *
     * @param string $key
     * @return boolean
     */
    public function allowed(string $key): bool
    {
        return in_array($key, $this->accessTokenKeys);
    }

    /**
     * Get allowed keys.
     *
     * @return array
     */
    public function allowedKeys(): array
    {
        return $this->accessTokenKeys;
    }
}
