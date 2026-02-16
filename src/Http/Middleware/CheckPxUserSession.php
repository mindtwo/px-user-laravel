<?php

namespace mindtwo\PxUserLaravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use mindtwo\PxUserLaravel\Contracts\PxUser;
use Symfony\Component\HttpFoundation\Response;

class CheckPxUserSession
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string|null  $driver  - The driver to load
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ?string $driver = null)
    {
        if ($request->user() instanceof PxUser) {
            $hasValidToken = false;
            try {
                $hasValidToken = $request->user()->hasValidPxUserToken();
            } catch (\Throwable $th) {
                // throw $th;
            }

            if (! $hasValidToken) {
                abort(401);
            }
        }

        return $next($request);
    }
}
