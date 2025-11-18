<?php

namespace mindtwo\PxUserLaravel\Http\Middleware;

use Illuminate\Http\Request;
use mindtwo\PxUserLaravel\Services\PxUserService;

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
            // Ensure the PxUserService is loaded with the specified driver
            $pxUser = resolve(PxUserService::class, ['driver' => $driver]);
            // ->loadConfig($driver);

            // Get the px user session for the current user
            $pxSession = $pxUser->session();

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
