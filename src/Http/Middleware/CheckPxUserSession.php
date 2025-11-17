<?php

namespace mindtwo\PxUserLaravel\Http\Middleware;

use Illuminate\Http\Request;
use mindtwo\PxUserLaravel\Driver\Contracts\SessionDriver;

class CheckPxUserSession
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string|null  $driver  - The driver to load
     */
    public function handle(Request $request, \Closure $next, ?string $driver = null)
    {
        if (! is_null($user = $request->user())) {
            // Get the px user session for the current user
            $pxSession = resolve(SessionDriver::class, ['driver' => $driver]);

            $pxSession->setUser($user);

            if (! $pxSession->validate()) {
                $pxSession->logout();

                $redirectTo = config('px-user.px_user_login_url', '/');

                return response()->redirectTo($redirectTo);

            }
        }

        return $next($request);
    }
}
