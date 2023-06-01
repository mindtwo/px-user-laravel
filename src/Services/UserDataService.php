<?php

namespace mindtwo\PxUserLaravel\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use mindtwo\PxUserLaravel\Actions\PxUserDataRefreshAction;
use mindtwo\PxUserLaravel\Actions\PxUserGetDetailsAction;

class UserDataService
{
    public function __construct(
        protected PxUserDataRefreshAction $pxUserDataRefreshAction,
        protected PxUserGetDetailsAction $pxUserDataGetDetailsAction,
    ) {
    }

    /**
     * Try to get cached UserData.
     * If no data are in Cache, try to
     * refresh them.
     *
     * @return mixed|void
     */
    public function getUserData(string $px_user_id)
    {
        if (App::environment(['testing'])) {
            return [];
        }

        if (App::runningInConsole()) {
            // TODO cache here?
            $pxAdmin = App::make(PxAdminClient::class);

            return $pxAdmin->user($px_user_id);
        }

        $currentUserId = Auth::user()?->{config('px-user.px_user_id')};

        if ($px_user_id === $currentUserId) {
            // Data for current user are cached via request middleware
            // Todo changeable domains/tenant in product
            // cache prefix
            $cachePrefix = ('user:cached_'.$px_user_id);

            // get user data from cache
            $userData = Cache::get($cachePrefix);

            // if we have no cached data forget delete old ones from cache
            if (empty($userData)) {
                Cache::forget($cachePrefix);
            }

            return $userData;
        }

        // cache data for other user
        return $this->pxUserDataGetDetailsAction->execute($px_user_id);
    }

    /**
     * Refresh data for current request.
     *
     * @return mixed
     */
    public function refreshUserData(Request $request)
    {
        $px_user_id = $request->user()->{config('px-user.px_user_id')};

        $cachePrefix = ('user:cached_'.$px_user_id);

        return Cache::remember(
            $cachePrefix,
            now()->addMinutes(config('px-user.px_user_cache_time')),
            fn () => $this->pxUserDataRefreshAction->execute()
        );
    }
}
