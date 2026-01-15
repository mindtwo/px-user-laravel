<?php

namespace mindtwo\PxUserLaravel\Services;

use mindtwo\PxUserLaravel\Contracts\PxUser;
use mindtwo\PxUserLaravel\DataTransfer\PxUserData;
use mindtwo\PxUserLaravel\DataTransfer\PxUserDataWithPermissions;
use mindtwo\PxUserLaravel\Http\Client\PxUserClient;
use mindtwo\TwoTility\Http\CachedApiService;

/**
 * @extends CachedApiService<PxUserClient>
 */
class PxUserCachedApiService extends CachedApiService
{
    /**
     * Get currently authed user's data with caching.
     */
    public function getUser(?int $ttl = null): PxUserData
    {
        $ttl = $ttl ?? config('px-user.px_user_cache_time', 120);

        $user = auth()->user();

        throw_if(! $user instanceof PxUser, 'Current authenticatable does not implement PxUser contract');

        return $this->cache->remember(
            $this->getCacheKey('px-user', $user),
            now()->addMinutes($ttl),
            fn () => $this->client()->getUser()
        );
    }

    /**
     * Get currently authed user's data with permissions and caching.
     */
    public function getUserWithPermissions(bool $withExtendedProducts = false, ?int $ttl = null): PxUserDataWithPermissions
    {
        $ttl = $ttl ?? config('px-user.px_user_cache_time', 120);

        $user = auth()->user();

        throw_if(! $user instanceof PxUser, 'Current authenticatable does not implement PxUser contract');

        return $this->cache->remember(
            $this->getCacheKey('px-user', $user),
            now()->addMinutes($ttl),
            fn () => $this->client()->getUserWithPermissions($withExtendedProducts)
        );
    }

    /**
     * Get the details for a user or list of users with caching.
     *
     * @param  string|array<int, string>  $userIds  Single user ID or array of user IDs
     * @param  int|null  $ttl  Cache TTL in minutes
     * @return ($userIds is string ? PxUserData : array<int, PxUserData>)
     */
    public function getUsersDetails(string|array $userIds, ?int $ttl = null): PxUserData|array
    {
        $ttl = $ttl ?? config('px-user.px_user_cache_time', 120);
        $single = is_string($userIds);

        if ($single) {
            $userIds = [$userIds];
        }

        $cachedUsers = [];
        $missingUserIds = [];

        // Check cache for each user
        foreach ($userIds as $userId) {
            $cacheKey = cache_key('px-user-details', ['id' => $userId])->toString();
            $cachedUser = $this->cache->get($cacheKey);

            if ($cachedUser instanceof PxUserData) {
                $cachedUsers[$userId] = $cachedUser;
            } else {
                $missingUserIds[] = $userId;
            }
        }

        // Fetch missing users from API
        if (! empty($missingUserIds)) {
            $fetchedUsers = $this->client()->getUsersDetails($missingUserIds);

            // Cache each fetched user individually
            foreach ($fetchedUsers as $userData) {
                $cacheKey = cache_key('px-user-details', ['id' => $userData->id])->toString();
                $this->cache->put($cacheKey, $userData, now()->addMinutes($ttl));
                $cachedUsers[$userData->id] = $userData;
            }
        }

        // Return single user or array
        if ($single) {
            return reset($cachedUsers);
        }

        return array_values($cachedUsers);
    }

    /**
     * Get the API client class name.
     *
     * @return class-string<PxUserClient>
     */
    protected function getClientClass(): string
    {
        return PxUserClient::class;
    }

    public function getCacheKey(string $name, PxUser $pxUser): string
    {
        return cache_key($name, [
            'class' => config('px-user.user_model'),
            'key' => $pxUser->getPxUserId(),
        ])->toString();
    }
}
