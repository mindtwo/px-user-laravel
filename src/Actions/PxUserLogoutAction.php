<?php

namespace mindtwo\PxUserLaravel\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use mindtwo\PxUserLaravel\Cache\UserDataCache;
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
            Cache::forget(cache_key('data_cache', [
                'name' => 'user',
                'uuid' => Auth::user()->{config('px-user.px_user_id')},
            ])->toString());
            new UserDataCache(Auth::user(), null, true);

            // TODO invalidate personal access token here

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
