<?php

namespace mindtwo\PxUserLaravel\Actions;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use mindtwo\PxUserLaravel\Helper\SessionHelper;
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
     * @param ?Request $request
     * @return ?array
     *
     * @throws Throwable
     */
    public function execute(string|array $px_user_id, ?Request $request = null): mixed
    {
        // get data for other user
        if (Gate::denies('user-detail')) {
            return null;
        }

        // if both token are expired return null
        if ($this->tokensExpired($request)) {
            return null;
        }

        // if auth token is expired try to get a new one
        if ($this->needsRefresh($request)) {
            $refresh_token = SessionHelper::get('px_user_refresh_token');
            $refreshed = $this->pxUserClient->refreshToken($refresh_token);

            // put new tokens into session
            SessionHelper::saveTokenData($refreshed, $request);
        }

        // cache get data for only one user
        if (gettype($px_user_id) === 'string') {
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
        $accessToken = SessionHelper::get('access_token');

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
        $accessToken = SessionHelper::get('access_token');

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

        return Cache::getMultiple($tags);
    }

    /**
     * Check if both tokens are expired.
     *
     * @return bool
     */
    private function tokensExpired(?Request $request): bool
    {
        // TODO remove?
        // if ($request->is('api/*')) {
        //     return SessionHelper::get('refresh_token') === null;
        // }

        $token_expired = Carbon::now()->gt(SessionHelper::get('access_token_expiration_utc'));
        $refresh_expired = Carbon::now()->gt(SessionHelper::get('refresh_token_expiration_utc'));

        return $token_expired && $refresh_expired;
    }

    private function needsRefresh(?Request $request): bool
    {
        // TODO remove?
        // if ($request->is('api/*')) {
        //     return SessionHelper::get('access_token') === null;
        // }

        SessionHelper::get('access_token_expiration_utc');
        $token_expired = Carbon::now()->gt(SessionHelper::get('access_token_expiration_utc'));
        $refresh_expired = Carbon::now()->gt(SessionHelper::get('refresh_token_expiration_utc'));

        return $token_expired && !$refresh_expired;
    }
}
