<?php

namespace mindtwo\PxUserLaravel\Services;

use Illuminate\Http\Client\RequestException;
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
     * ID is looked up without context. A user unknown to PX resolves to null and
     * is remembered as missing for `px_user_miss_cache_time` minutes, so repeated
     * lookups don't hit PX again.
     *
     * @throws RequestException for any PX error other than 404
     */
    public function getUser(string|PxUser $user): ?PxUserData
    {
        $userId = $user instanceof PxUser ? $user->getPxUserId() : $user;
        $missCacheKey = $this->missCacheKey($userId);

        if ($this->cache->has($missCacheKey)) {
            return null;
        }

        $ttl = config('px-user.px_user_cache_time', 120);

        try {
            return $this->cache->remember(
                Utils::getPxUserCacheKey($userId),
                now()->addMinutes($ttl),
                fn () => $user instanceof PxUser
                    ? $this->client()->getUser($userId, $user->getPxUserTenantCode(), $user->getPxUserDomainCode())
                    : $this->client()->getUser($userId)
            );
        } catch (RequestException $exception) {
            if ($exception->response->status() !== 404) {
                throw $exception;
            }

            $this->cache->put(
                $missCacheKey,
                true,
                now()->addMinutes((int) config('px-user.px_user_miss_cache_time', 5)),
            );

            return null;
        }
    }

    /**
     * Cache key marking a user as unknown to PX.
     */
    private function missCacheKey(string $userId): string
    {
        return cache_key('px-user-miss', [
            'class' => config('px-user.user_model'),
            'key' => $userId,
        ])->toString();
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
