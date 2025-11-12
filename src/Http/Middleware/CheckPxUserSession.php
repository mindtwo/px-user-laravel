<?php

namespace mindtwo\PxUserLaravel\Http\Middleware;

use Illuminate\Http\Request;

class CheckPxUserSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, \Closure $next, ?string $guard = null)
    {
        if (! is_null($user = $request->user())) {
            $pxSession = px_user()->session($guard);

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
