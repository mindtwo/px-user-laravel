<?php

namespace mindtwo\PxUserLaravel\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use mindtwo\PxUserLaravel\Facades\AccessTokenHelper;
use mindtwo\PxUserLaravel\Services\CheckUserTokenService;
use mindtwo\PxUserLaravel\Services\PxUserClient;

class PxUserGetDetailsAction
{
    public function __construct(
        protected PxUserClient $pxUserClient,
    ) {
    }

    /**
     * Undocumented function.
     *
     * @param array $px_user_ids
     * @return ?array
     *
     * @throws Throwable
     */
    public function execute($px_user_id): mixed
    {
        // get data for other user
        if (Gate::denies('user-detail')) {
            return null;
        }

        $checkUserTokenService = app()->make(CheckUserTokenService::class);
        // if auth token is expired try to get a new one
        if (!$checkUserTokenService->check()) {
            return null;
        }

        // cache get data for only one user
        if (!is_array($px_user_id)) {
            return $this->cacheUserDetail($px_user_id);
        }

        return $this->cacheMultipleUserDetails($px_user_id);
    }

    /**
     * Get user details for one user, load them via API if user is not in cache.
     *
     * @param string $px_user_id
     * @return mixed
     */
    private function cacheUserDetail(string $px_user_id): mixed
    {
        $cachePrefix = "user_detail:cached_{$px_user_id}";

        // fetch with session token
        $accessToken = AccessTokenHelper::get('access_token');

        return Cache::remember(
            $cachePrefix,
            now()->addMinutes(config('px-user.px_user_cache_time')),
            fn () => optional($this->pxUserClient->getUserDetails($accessToken, [$px_user_id]))[0] ?? [],
        );
    }

    /**
     * Get user details for one user, load them via API if user is not in cache.
     *
     * @param string $px_user_id
     * @return mixed
     */
    private function cacheMultipleUserDetails(array $px_user_ids): mixed
    {
        // get all cached users
        $tags = collect($px_user_ids)->map(fn ($id) => "user_detail:cached_{$id}")->toArray();
        $cachedDetails = Cache::getMultiple($tags);

        // get only ids
        $cachedIds = collect($cachedDetails)->pluck('id');

        // fetch with session token
        $accessToken = AccessTokenHelper::get('access_token');

        // refresh details for users not in cache
        $userDetails = $this->pxUserClient->getUserDetails($accessToken, collect($px_user_ids)->diff($cachedIds)->toArray()) ?? [];

        foreach ($userDetails as $user) {
            $cachePrefix = "user_detail:cached_{$user['id']}";

            // set a value into cache if no user is foun
            Cache::put(
                $cachePrefix,
                $user,
                now()->addMinutes(config('px-user.px_user_cache_time')),
            );
        }

        return [
            ...collect($cachedDetails)->filter()->toArray(),
            ...$userDetails,
        ];
    }
}
