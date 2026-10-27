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
     * Logs the user out of a stateful guard when no token can be refreshed and passes the
     * request on as a guest, so the authentication middleware after it answers as it does
     * for any guest. Aborts with 401 for a guard it cannot log out of.
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
        }

        return $next($request);
    }

    /**
     * Handle a refresh that left the user without a valid token, after the session has ended.
     *
     * Does nothing by default. Override it to add handling, or throw to answer the request
     * yourself instead of passing it on as a guest.
     *
     * @param  string|null  $guard  - The guard the user was authenticated with, the default guard if null
     */
    protected function failedRefresh(Request $request, ContractsPxUser $user, ?string $guard): void
    {
        //
    }

    /**
     * End the web session, the same way a logout does.
     */
    protected function endSession(Request $request, ?string $guard): void
    {
        $authGuard = $this->auth->guard($guard);

        if (! $authGuard instanceof StatefulGuard) {
            abort(401);
        }

        $authGuard->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }
}
