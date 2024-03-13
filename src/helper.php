<?php

if (! function_exists('px_session')) {
    function px_session(?string $guard = null): \mindtwo\PxUserLaravel\Driver\Contracts\SessionDriver
    {
        return px_user()->session($guard);
    }
}

if (! function_exists('px_user')) {
    function px_user(): \mindtwo\PxUserLaravel\Services\PxUserService
    {
        return app('px-user');
    }
}

if (! function_exists('active_guard')) {
    function active_guard(?string $default = null): ?string
    {
        foreach (array_keys(config('auth.guards')) as $guard) {
            if (auth()->guard($guard)->check()) {
                return $guard;
            }
        }

        return $default;
    }
}
