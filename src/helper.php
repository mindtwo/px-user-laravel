<?php

if (! function_exists('px_session')) {
    function px_session(?string $guard = null): \mindtwo\PxUserLaravel\Driver\Contracts\SessionDriver
    {
        $session = px_user()->session($guard);
        if (! $session) {
            throw new \Exception('Session driver not found');
        }

        return $session;
    }
}

if (! function_exists('px_user')) {
    function px_user(): \mindtwo\PxUserLaravel\Services\PxUserService
    {
        return app(\mindtwo\PxUserLaravel\Services\PxUserService::class);
    }
}

if (! function_exists('active_guard')) {
    function active_guard(?string $default = null): ?string
    {
        foreach (array_keys(config('auth.guards')) as $guard) {
            if (! is_string($guard)) {
                continue;
            }

            if (auth()->guard($guard)->check()) {
                return $guard;
            }
        }

        return $default;
    }
}
