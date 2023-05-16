<?php

namespace mindtwo\PxUserLaravel\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use mindtwo\PxUserLaravel\Facades\AccessTokenHelper;

class PxUserLogoutAction
{
    /**
     * Logout user
     *
     *
     * @throws Exception
     */
    public function execute(): bool
    {
        try {
            $px_user_id = Auth::user()->{config('px-user.px_user_id')};
            $cachePrefix = ('user:cached_'.$px_user_id);
            // forget user data in cache
            Cache::forget($cachePrefix);

            AccessTokenHelper::flush();

            if (method_exists(app('auth'), 'logout')) {
                optional((app('auth')))->logout();
            }
        } catch (\Throwable $th) {
            throw $th;

            return false;
        }

        return true;
    }
}
