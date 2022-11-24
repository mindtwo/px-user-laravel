<?php

namespace Mindtwo\PxUserLaravel\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Mindtwo\PxUserLaravel\Actions\PxUserDataRefreshAction;

class CacheUserData
{
    public function __construct(
        protected PxUserDataRefreshAction $pxUserDataRefreshAction,
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
        if (Auth::check()) {
            $px_user_id = Auth::user()->{config('px-user.px_user_id')};

            $cachePrefix = ('user:cached_'.$px_user_id);

            // cache user data for specified time period
            $userData = Cache::remember($cachePrefix, now()->addMinutes(config('px-user.cache_time')), function () {
                return $this->pxUserDataRefreshAction->execute();
            });

            // log the user out if we can not refresh
            if (empty($userData)) {
                Auth::logout();

                $request->session()->invalidate();

                $request->session()->regenerateToken();
            }
        }

        return $next($request);
    }
}
