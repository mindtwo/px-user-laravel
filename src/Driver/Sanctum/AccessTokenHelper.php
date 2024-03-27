<?php

namespace mindtwo\PxUserLaravel\Driver\Sanctum;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use mindtwo\PxUserLaravel\Driver\Contracts\AccessTokenHelper as ContractsAccessTokenHelper;
use mindtwo\PxUserLaravel\Facades\PxUser;

class AccessTokenHelper implements ContractsAccessTokenHelper
{
    public function __construct(
        private ?Authenticatable $user = null,
    ) {

    }

    /**
     * Save token data either to cache or session
     */
    public function saveTokenData(array $tokenData): void
    {
        foreach ($this->allowedKeys() as $key) {
            if (isset($tokenData[$key])) {
                $this->put($key, $tokenData[$key], $this->getKeyTtl($key, $tokenData));
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
        foreach ($this->allowedKeys() as $key) {
            Cache::forget($this->getCacheKey($key));
        }
    }

    /**
     * Put value for passed key
     */
    public function put(string $key, mixed $value, null|int|DateTimeInterface $dateTimeInterface = null): void
    {
        if (! $this->allowed($key)) {
            throw new \Exception('Error Processing Request', 1);
        }

        $validUntil = $dateTimeInterface ?? now()->addMinutes(config('px-user.driver.sanctum.max_cache_time', 720));

        Cache::put($this->getCacheKey($key), $value, $validUntil);
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

        return Cache::get($this->getCacheKey($key));
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

    private function getCacheKey(string $key): string
    {
        return cache_key(config('px-user.session_prefix') ?? 'px_user', [
            $this->user->{config('px-user.px_user_id')},
            $key,
        ])->debugIf(config('px-user.debug'))->toString();
    }

    private function getKeyTtl(string $key, array $tokenData): int
    {
        if (config('px-user.driver.sanctum.use_api_ttl', false)) {
            // TODO implement this

            $expirationUtc = $tokenData["{$key}_expiration_utc"] ?? null;

            return $expirationUtc ? Carbon::parse($expirationUtc)->diffInMinutes(now()) : config('px-user.driver.sanctum.max_cache_time', 720);
        }

        return config('px-user.driver.sanctum.max_cache_time', 720);
    }
}
