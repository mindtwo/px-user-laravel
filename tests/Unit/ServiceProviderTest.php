<?php

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\EngineManager;
use mindtwo\PxUserLaravel\Http\Client\PxUserAdminClient;
use mindtwo\PxUserLaravel\Http\Client\PxUserClient;
use mindtwo\PxUserLaravel\Http\Middleware\RefreshPxUserSession;
use mindtwo\PxUserLaravel\Scout\PxUserEngine;
use mindtwo\PxUserLaravel\Services\PxUserCachedApiService;
use mindtwo\PxUserLaravel\Services\PxUserTokens;

test('PxUserClient is registered in service container', function () {
    $client = app(PxUserClient::class);

    expect($client)->toBeInstanceOf(PxUserClient::class);
});

test('PxUserAdminClient is registered in service container', function () {
    $client = app(PxUserAdminClient::class);

    expect($client)->toBeInstanceOf(PxUserAdminClient::class);
});

test('PxUserCachedApiService is registered in service container', function () {
    $service = app(PxUserCachedApiService::class);

    expect($service)->toBeInstanceOf(PxUserCachedApiService::class);
});

test('PxUserTokens service is resolvable from container', function () {
    $service = app(PxUserTokens::class);

    expect($service)->toBeInstanceOf(PxUserTokens::class);
});

test('scout engine is registered', function () {
    $manager = app(EngineManager::class);

    expect($manager->engine('px-user'))->toBeInstanceOf(PxUserEngine::class);
});

test('config is merged from package', function () {
    expect(config('px-user'))->not->toBeNull()
        ->and(config('px-user.px_user_id'))->toBe('px_user_id')
        ->and(config('px-user.px_user_cache_time'))->toBe(120);
});

test('RefreshPxUserSession runs before the authentication middleware', function () {
    $priority = app(HttpKernel::class)->getMiddlewarePriority();

    expect(array_search(RefreshPxUserSession::class, $priority, true))
        ->toBe(array_search(AuthenticatesRequests::class, $priority, true) - 1);
});

test('the authentication middleware does not move ahead of RefreshPxUserSession in a web route', function () {
    // The kernel registers the middleware groups, aliases and priority with the router
    app(HttpKernel::class);
    Route::middleware(['web', RefreshPxUserSession::class, 'auth'])->get('/px-priority', fn () => 'OK');

    $middleware = app(Router::class)->gatherRouteMiddleware(app(Router::class)->getRoutes()->match(Request::create('/px-priority')));

    expect($middleware)->toContain(SubstituteBindings::class, Authenticate::class)
        ->and(array_search(RefreshPxUserSession::class, $middleware, true))
        ->toBeLessThan(array_search(Authenticate::class, $middleware, true));
});
