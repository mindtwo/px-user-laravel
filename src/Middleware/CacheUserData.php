<?php

namespace mindtwo\PxUserLaravel\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use mindtwo\PxUserLaravel\Actions\PxUserDataRefreshAction;
use mindtwo\PxUserLaravel\Actions\PxUserLogoutAction;

class CacheUserData
{
    public function __construct(
        protected PxUserDataRefreshAction $pxUserDataRefreshAction,
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

            // cache user data for specified time period
            $userData = Cache::remember($cachePrefix, now()->addMinutes(config('px-user.px_user_cache_time')), function () use ($request) {
                return $this->pxUserDataRefreshAction->execute($request);
            });

            // log the user out if we can not refresh
            if (empty($userData)) {
                $this->pxUserLogoutAction->execute($request);
            }
        }

        return $next($request);
    }
}
