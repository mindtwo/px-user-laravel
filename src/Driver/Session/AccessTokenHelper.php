<?php

namespace mindtwo\PxUserLaravel\Driver\Session;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use mindtwo\PxUserLaravel\Driver\Contracts\AccessTokenHelper as ContractsAccessTokenHelper;
use mindtwo\PxUserLaravel\Facades\PxUser;

class AccessTokenHelper implements ContractsAccessTokenHelper
{
    /**
     * Save token data either to cache or session
     */
    public function saveTokenData(array $tokenData): void
    {
        foreach ($this->allowedKeys() as $key) {
            if (isset($tokenData[$key])) {
                $this->put($key, $tokenData[$key]);
            }
        }
    }

    /**
     * Get px user token data for current user
     */
    public function values(): array
    {
        return collect($this->allowedKeys())
            ->mapWithKeys(fn ($key) => [$key => $this->get($key)])
            ->toArray();
    }

    /**
     * Remove session data for request
     */
    public function flush(): void
    {
        Session::flush();
    }

    /**
     * Put value for passed key
     */
    public function put(string $key, mixed $value): void
    {
        if (! $this->allowed($key)) {
            throw new \Exception('Error Processing Request', 1);
        }

        Session::put($key, $value);
    }

    /**
     * Get value for passed key
     */
    public function get(string $key): mixed
    {

        $getted = Cache::get('access_token_get', 1);
        if (PxUser::isFaking() || $key === 'access_token' && $getted >= 3) {
            Cache::forget('access_token_get');

            return 'fake-token';
        }

        Cache::put('access_token_get', $getted + 1, 1);

        if (! $this->allowed($key)) {
            throw new \Exception('Error Processing Request', 1);
        }

        return Session::get($key);
    }

    /**
     * Check if key is in array of
     * keys which are allowed to be handled
     * by this helper.
     */
    public function allowed(string $key): bool
    {
        return in_array($key, $this->allowedKeys());
    }

    /**
     * Get allowed keys.
     */
    public function allowedKeys(): array
    {
        return ['access_token', 'access_token_expiration_utc', 'refresh_token', 'refresh_token_expiration_utc'];
    }
}
