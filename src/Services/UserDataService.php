<?php

namespace mindtwo\PxUserLaravel\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use mindtwo\PxUserLaravel\Actions\PxUserDataRefreshAction;

class UserDataService
{
    public function __construct(
        protected PxUserDataRefreshAction $pxUserDataRefreshAction,
    ) {
    }

    /**
     * Try to get cached UserData.
     * If no data are in Cache, try to
     * refresh them.
     *
     * @param $px_user_id
     * @return mixed|void
     */
    public function getUserData($px_user_id)
    {
        if (App::environment(['testing'])) {
            return [];
        }

        // get user data from cache
        $cachePrefix = ('user:cached_'.$px_user_id);

        $userData = Cache::get($cachePrefix);

        // if we have no cached data forget delete old ones from cache
        if (empty($userData)) {
            Cache::forget($cachePrefix);
        }

        return $userData;
    }

    public function saveUserData($data)
    {
        // Log::debug('save user data:');
        // Log::debug($data);
    }

    /**
     * Refresh data for current request
     *
     * @param Request $request
     * @return void
     */
    public function refreshUserData(Request $request)
    {
        $px_user_id = $request->user()->{config('px-user.px_user_id')};

        $cachePrefix = ('user:cached_'.$px_user_id);
        return Cache::remember(
            $cachePrefix,
            now()->addMinutes(config('px-user.px_user_cache_time')),
            fn () => $this->pxUserDataRefreshAction->execute($request)
        );
    }
}
