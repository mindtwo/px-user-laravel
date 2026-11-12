<?php

namespace mindtwo\PxUserLaravel\Services;

use mindtwo\PxUserLaravel\Contracts\PxUser;
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
     * Get a user's data with m2m and caching.
     *
     * Pass the PxUser to look the user up in their own tenant and domain; a plain
     * ID is looked up without context.
     */
    public function getUser(string|PxUser $user): ?PxUserData
    {
        $userId = $user instanceof PxUser ? $user->getPxUserId() : $user;
        $ttl = config('px-user.px_user_cache_time', 120);

        return $this->cache->remember(
            Utils::getPxUserCacheKey($userId),
            now()->addMinutes($ttl),
            fn () => $user instanceof PxUser
                ? $this->client()->getUser($userId, $user->getPxUserTenantCode(), $user->getPxUserDomainCode())
                : $this->client()->getUser($userId)
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
