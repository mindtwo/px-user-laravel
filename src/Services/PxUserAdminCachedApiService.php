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
    public function getUser(string $userId): ?PxUserData
    {
        $ttl = $ttl ?? config('px-user.px_user_cache_time', 120);

        return $this->cache->remember(
            Utils::getPxUserCacheKey($userId, 'px-user:admin'),
            now()->addMinutes($ttl),
            fn () => $this->client()->getUser($userId)
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
