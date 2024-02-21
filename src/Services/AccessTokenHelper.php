<?php

namespace mindtwo\PxUserLaravel\Services;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use mindtwo\PxUserLaravel\Facades\PxUser;

class AccessTokenHelper
{
    public function __construct(
        public Authenticatable $user,
    ) {
    }

    private array $accessTokenKeys = ['access_token', 'access_token_expiration_utc', 'refresh_token', 'refresh_token_expiration_utc'];

    /**
     * Check if access token is expired.
     */
    public function accessTokenExpired(): bool
    {
        if (PxUser::isFaking()) {
            return false;
        }

        if (null === ($time = $this->get('access_token_expiration_utc'))) {
            return true;
        }

        return Carbon::now()->gt($time);
    }

    /**
     * Check if access token is expiring soon.
     */
    public function accessTokenExpiringSoon(): bool
    {
        if (PxUser::isFaking()) {
            return false;
        }

        if (null === ($time = $this->get('access_token_expiration_utc'))) {
            return false;
        }

        return Carbon::now()->addMinutes(15)->gte($time);
    }

    /**
     * Check if tokens can be refreshed.
     */
    public function canRefresh(): bool
    {
        if ($this->get('refresh_token') === null || ($time = $this->get('refresh_token_expiration_utc')) === null) {
            return false;
        }

        return Carbon::now()->lt($time);
    }

    /**
     * Save token data either to cache or session
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
    public function flush()
    {
        foreach ($this->allowedKeys() as $key) {
            $sessionKey = cache_key(config('px-user.session_prefix') ?? 'px_user', [
                $this->user->{config('px-user.px_user_id')},
                $key,
            ]);

            Cache::forget($sessionKey);
        }
    }

    /**
     * Put value for passed key
     *
     * @return void
     */
    public function put(string $key, string $value)
    {
        if (! $this->allowed($key)) {
            throw new \Exception('Error Processing Request', 1);
        }

        $sessionKey = cache_key(config('px-user.session_prefix') ?? 'px_user', [
            $this->user->{config('px-user.px_user_id')},
            $key,
        ]);

        Cache::put($sessionKey, $value);
    }

    /**
     * Get value for passed key
     */
    public function get(string $key): mixed
    {
        if (PxUser::isFaking()) {
            return 'fake-token';
        }

        if (! $this->allowed($key)) {
            throw new \Exception('Error Processing Request', 1);
        }

        $sessionKey = cache_key(config('px-user.session_prefix') ?? 'px_user', [
            $this->user->{config('px-user.px_user_id')},
            $key,
        ]);

        return Cache::get($sessionKey);
    }

    /**
     * Check if key is in array of
     * keys which are allowed to be handled
     * by this helper.
     */
    public function allowed(string $key): bool
    {
        return in_array($key, $this->accessTokenKeys);
    }

    /**
     * Get allowed keys.
     */
    public function allowedKeys(): array
    {
        return $this->accessTokenKeys;
    }
}
