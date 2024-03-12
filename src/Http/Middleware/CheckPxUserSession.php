<?php

namespace mindtwo\PxUserLaravel\Http\Middleware;

use Illuminate\Http\Request;
use mindtwo\PxUserLaravel\Facades\PxUserSession;

class CheckPxUserSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, \Closure $next)
    {

        if ($request->user()) {
            $pxSession = PxUserSession::driver();

            $pxSession->setUser($request->user());

            if (! $pxSession->valid()) {
                $pxSession->logout();
                abort(401);
            }
        }

        return $next($request);
    }
}
