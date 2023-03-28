<?php

namespace mindtwo\PxUserLaravel\Actions;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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

        // fetch with session token
        $accessToken = SessionHelper::get('access_token');
        $auth_user_id = Auth::user()->{config('px-user.px_user_id')};

        // cache get data for only one user
        if (!is_array($px_user_id)) {
            $cachePrefix = "user:cached_{$auth_user_id}:user_{$px_user_id}";

            return Cache::remember(
                $cachePrefix,
                now()->addMinutes(config('px-user.px_user_cache_time')),
                fn () => optional($this->pxUserClient->getUserDetails($accessToken, [$px_user_id]))[0] ?? [],
            );
        }

        // get cached user data
        $cachedUserData = $this->collectCachedUserData($auth_user_id, $px_user_id);

        // get ids we need to refresh
        $idsNeedingRefresh = collect($px_user_id)->diff($cachedUserData->pluck('id'))->values()->toArray();

        // cache multiple users
        $userData = collect($this->pxUserClient->getUserDetails($accessToken, $idsNeedingRefresh) ?? []);
        foreach ($idsNeedingRefresh as $user_id) {
            $cachePrefix = "user:cached_{$auth_user_id}:user_{$user_id}";
            // set a value into cache if no user is found
            $data = collect($userData)->filter(fn ($entry) => $entry['id'] === $user_id)->first() ?? ['no access'];

            Cache::put(
                $cachePrefix,
                $data,
                now()->addMinutes(config('px-user.px_user_cache_time')),
            );
        }

        return $userData->merge($cachedUserData);
    }

    private function collectCachedUserData(string $auth_user_id, array $px_user_ids)
    {
        $cachePrefix = "user:cached_{$auth_user_id}:user";
        return collect($px_user_ids)->map(fn ($user_id) => Cache::get("{$cachePrefix}_{$user_id}"));
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
