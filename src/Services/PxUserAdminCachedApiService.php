<?php

namespace mindtwo\PxUserLaravel\Services;

use mindtwo\PxUserLaravel\DataTransfer\PxUserData;
use mindtwo\PxUserLaravel\Helper\Utils;
use mindtwo\PxUserLaravel\Http\Client\PxUserAdminClient;
use mindtwo\TwoTility\Http\CachedApiService;

/**
 * @extends CachedApiService<PxUserAdminClient>
 */
class PxUserAdminCachedApiService extends CachedApiService
{
    /**
     * Get a user's data with m2m and caching, looked up in the user's own tenant and domain.
     */
    public function getUser(string $userId, string $tenantCode, string $domainCode): ?PxUserData
    {
        $ttl = config('px-user.px_user_cache_time', 120);

        return $this->cache->remember(
            Utils::getPxUserCacheKey($userId),
            now()->addMinutes($ttl),
            fn () => $this->client()->getUser($userId, $tenantCode, $domainCode)
        );
    }

    /**
     * Get the API client class name.
     *
     * @return class-string<PxUserAdminClient>
     */
    protected function getClientClass(): string
    {
        return PxUserAdminClient::class;
    }
}
