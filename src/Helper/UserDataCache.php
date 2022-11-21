<?php

namespace Mindtwo\PxUserLaravel\Helper;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class UserDataCache
{
    /**
     * Try to get cached UserData.
     * If no data are in Cache, try to
     * refresh them.
     *
     * @param $px_user_id
     * @return mixed|void
     */
    public static function getUserData($px_user_id)
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
}
