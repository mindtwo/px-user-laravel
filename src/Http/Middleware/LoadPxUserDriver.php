<?php

namespace mindtwo\PxUserLaravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use mindtwo\PxUserLaravel\Services\PxUserService;
use Symfony\Component\HttpFoundation\Response;

class LoadPxUserDriver
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string|null  $driver  - The driver to load
     */
    public function handle(Request $request, Closure $next, ?string $driver = null): Response
    {
        if ($driver !== null) {
            // Ensure the PxUserService is loaded with the specified driver
            resolve(PxUserService::class, ['driver' => $driver])
                ->loadConfig($driver);
        }

        return $next($request);
    }
}
