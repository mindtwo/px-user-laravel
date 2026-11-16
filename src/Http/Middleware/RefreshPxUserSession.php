<?php

namespace mindtwo\PxUserLaravel\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use mindtwo\PxUserLaravel\Contracts\PxUser as ContractsPxUser;
use mindtwo\PxUserLaravel\PxUser;
use Symfony\Component\HttpFoundation\Response;

class RefreshPxUserSession
{
    public function __construct(
        private readonly PxUser $pxUser,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * Refresh the authenticated user's PX tokens when they are about to expire.
     *
     * When no token can be refreshed, it ends the session, runs `failedRefresh()` and then
     * `unauthenticated()`, which aborts with 401 on a guard without a session and otherwise
     * passes the request on as a guest for the authentication middleware after it.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string|null  $guard  - The guard the user is authenticated with, the default guard if null
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ?string $guard = null)
    {
        $user = $request->user($guard);

        if ($user instanceof ContractsPxUser && ! $this->pxUser->refreshSession($user)) {
            $this->endSession($request, $guard);
            $this->failedRefresh($request, $user, $guard);
            $this->unauthenticated($request, $guard);
        }

        return $next($request);
    }

    /**
     * Handle a refresh that left the user without a valid token, after the session has ended.
     *
     * Does nothing by default and runs for every guard. Override it to add handling, or throw
     * to answer the request yourself instead of the 401 or passing it on as a guest.
     *
     * @param  string|null  $guard  - The guard the user was authenticated with, the default guard if null
     */
    protected function failedRefresh(Request $request, ContractsPxUser $user, ?string $guard): void
    {
        //
    }

    /**
     * Log the user out and end the web session, the same way a logout does.
     *
     * Does nothing for a guard without a session, such as a bearer token guard.
     */
    protected function endSession(Request $request, ?string $guard): void
    {
        $authGuard = $this->auth->guard($guard);

        if (! $authGuard instanceof StatefulGuard) {
            return;
        }

        $authGuard->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    /**
     * Abort with 401 on a guard without a session; on a stateful guard the request passes on as a guest.
     */
    protected function unauthenticated(Request $request, ?string $guard): void
    {
        if (! $this->auth->guard($guard) instanceof StatefulGuard) {
            abort(401);
        }
    }
}
