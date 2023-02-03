<?php

namespace mindtwo\PxUserLaravel\Middleware;

use Closure;
use Illuminate\Http\Request;
use mindtwo\PxUserLaravel\Actions\PxUserLogoutAction;
use mindtwo\PxUserLaravel\Facades\UserDataCache;

class CacheUserData
{
    public function __construct(
        protected PxUserLogoutAction $pxUserLogoutAction,
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            $px_user_id = $request->user()->{config('px-user.px_user_id')};

            $cachePrefix = ('user:cached_'.$px_user_id);

            // Log::debug('session:');
            // Log::debug($request->session()->all());

            try {
                // cache user data for specified time period
                $userData = UserDataCache::refreshUserData($request);
            } catch (\Throwable $th) {
                // TODO handle exception here?

                return $next($request);
            }

            // log the user out if we can not refresh
            if (empty($userData)) {
                $this->pxUserLogoutAction->execute($request);
            }
        }

        return $next($request);
    }
}
