<?php

namespace mindtwo\PxUserLaravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use mindtwo\PxUserLaravel\Contracts\PxUser as ContractsPxUser;
use mindtwo\PxUserLaravel\PxUser;
use Symfony\Component\HttpFoundation\Response;

class RefreshPxUserSession
{
    public function __construct(
        private readonly PxUser $pxUser,
    ) {}

    /**
     * Refresh the authenticated user's PX tokens when they are about to expire.
     *
     * Aborts with 401 when the user holds no token that can be refreshed.
     *
     * @param  Closure(Request): (Response)  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user instanceof ContractsPxUser && ! $this->pxUser->refreshSession($user)) {
            abort(401);
        }

        return $next($request);
    }
}
