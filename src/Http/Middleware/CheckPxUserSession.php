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
        if ($request->user()) {
            $pxSession = px_session($guard);

            $pxSession->setUser($request->user());
            if (! $pxSession->valid()) {
                $pxSession->logout();
                abort(401);
            }
        }

        return $next($request);
    }
}
