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

    // Todo tenant/domain code
    /**
     * Try to get cached UserData.
     * If no data are in Cache, try to
     * refresh them.
     *
     * @param $px_user_id
     * @return mixed|void
     */
    public function getUserData(string $px_user_id)
    {
        if (App::environment(['testing'])) {
            return [];
        }

        // get data for other user
        if ($px_user_id !== Auth::user()->{config('px-user.px_user_id')}) {
            $auth_user_id = Auth::user()->{config('px-user.px_user_id')};

            $cachePrefix = "user:cached_{$auth_user_id}:user_{$px_user_id}";

            $data = $this->pxUserDataGetDetailsAction->execute($px_user_id);

            return $data;
        }

        // Data for current user are cached via request middleware
        // Todo changeable domains/tenant in product
        // cache prefix
        $cachePrefix = ('user:cached_' . $px_user_id);

        // get user data from cache
        $userData = Cache::get($cachePrefix);

        // if we have no cached data forget delete old ones from cache
        if (empty($userData)) {
            Cache::forget($cachePrefix);
        }

        return $userData;
    }

    /**
     * Refresh data for current request.
     *
     * @param Request $request
     * @return void
     */
    public function refreshUserData(Request $request)
    {
        $px_user_id = $request->user()->{config('px-user.px_user_id')};

        $cachePrefix = ('user:cached_' . $px_user_id);

        return Cache::remember(
            $cachePrefix,
            now()->addMinutes(config('px-user.px_user_cache_time')),
            fn () => $this->pxUserDataRefreshAction->execute($request)
        );
    }
}
