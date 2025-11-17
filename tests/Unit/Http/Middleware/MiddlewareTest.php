<?php

use Illuminate\Http\Request;
use mindtwo\PxUserLaravel\Driver\Contracts\SessionDriver;
use mindtwo\PxUserLaravel\Driver\Sanctum\SanctumSessionDriver;
use mindtwo\PxUserLaravel\Driver\Session\WebSessionDriver;
use mindtwo\PxUserLaravel\Http\Middleware\CheckPxUserSession;
use mindtwo\PxUserLaravel\Http\Middleware\LoadPxUserDriver;
use mindtwo\PxUserLaravel\Services\PxUserService;
use mindtwo\PxUserLaravel\Tests\Fake\User;
use Mockery;

describe('LoadPxUserDriver Middleware', function () {
    it('should load the PxUserService with the specified driver', function () {

        // Expect the default driver to be set to sanctum
        expect(config('px-user.driver.default'))->toBe('sanctum');

        $middleware = new LoadPxUserDriver;

        $request = Request::create('/test', 'GET');
        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        expect($response->getContent())->toBe('OK');
        expect(resolve(PxUserService::class)->driver())->toBe('sanctum')
            ->and(resolve(SessionDriver::class))->toBeInstanceOf(SanctumSessionDriver::class);
    });

    it('overrides the driver when specified', function () {
        // Expect the default driver to be set to sanctum
        expect(config('px-user.driver.default'))->toBe('sanctum');

        $middleware = new LoadPxUserDriver;

        $request = Request::create('/test', 'GET');
        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        }, 'web');

        expect($response->getContent())->toBe('OK');
        expect(resolve(PxUserService::class)->driver())->toBe('web')
            ->and(resolve(SessionDriver::class))->toBeInstanceOf(WebSessionDriver::class);
    });
});

describe('CheckPxUserSession Middleware', function () {
    it('should validate the user session and redirect if invalid', function () {
        $middleware = new CheckPxUserSession;

        $request = Request::create('/test', 'GET');
        $request = Request::create('/test', 'GET')->setUserResolver(function () {
            return User::factory()->create(); // Simulate an authenticated user
        });

        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        expect($response->getStatusCode())->toBe(302)
            ->and($response->headers->get('Location'))->toBe(url(config('px-user.px_user_login_url', '/')));
    });
});

describe('Middleware stack', function () {

    beforeEach(function () {
        // Mock the session driver
        $mockDriver = Mockery::mock(WebSessionDriver::class)->makePartial();
        $mockDriver->shouldReceive('validate')->andReturn(true);
        $mockDriver->shouldReceive('setUser')->andReturnSelf();

        // Mock PxUserService to return our mocked driver
        $mockService = Mockery::mock(PxUserService::class)->makePartial();
        $mockService->shouldReceive('session')->andReturn($mockDriver);
        $mockService->shouldReceive('driver')->andReturn('web');

        // Bind the mocked service to the container
        app()->scoped(PxUserService::class, fn () => $mockService);
    });

    test('CheckPxUserSession should not override LoadPxUserDriver in middleware stack', function () {
        expect(config('px-user.driver.default'))->toBe('sanctum');

        $request = Request::create('/test', 'GET')->setUserResolver(function () {
            return User::factory()->create(); // Simulate an authenticated user
        });
        // Simulate middleware stack by chaining from innermost to outermost
        // The stack executes: LoadPxUserDriver -> CheckPxUserSession -> Controller
        $loadDriver = new LoadPxUserDriver;
        $checkSession = new CheckPxUserSession;

        // Start with the final handler (controller)
        $finalHandler = fn ($req) => response('OK');

        // Wrap with CheckPxUserSession (with 'sanctum' parameter)
        $wrappedWithCheck = fn ($req) => $checkSession->handle($req, $finalHandler);

        // Wrap with LoadPxUserDriver (with 'web' parameter)
        $response = $loadDriver->handle($request, $wrappedWithCheck, 'web');

        expect($response->getContent())->toBe('OK');
        expect(resolve(PxUserService::class)->driver())->toBe('web')
            ->and(resolve(SessionDriver::class))->toBeInstanceOf(WebSessionDriver::class);
    });

    test('CheckPxUserSession should not explicitly override LoadPxUserDriver in middleware stack', function () {
        expect(config('px-user.driver.default'))->toBe('sanctum');

        $request = Request::create('/test', 'GET')->setUserResolver(function () {
            return User::factory()->create(); // Simulate an authenticated user
        });

        // Simulate middleware stack by chaining from innermost to outermost
        // The stack executes: LoadPxUserDriver -> CheckPxUserSession -> Controller
        $loadDriver = new LoadPxUserDriver;
        $checkSession = new CheckPxUserSession;

        // Start with the final handler (controller)
        $finalHandler = fn ($req) => response('OK');

        // Wrap with CheckPxUserSession (with 'sanctum' parameter)
        $wrappedWithCheck = fn ($req) => $checkSession->handle($req, $finalHandler, 'sanctum');

        // Wrap with LoadPxUserDriver (with 'web' parameter)
        $response = $loadDriver->handle($request, $wrappedWithCheck, 'web');

        expect($response->getContent())->toBe('OK');
        expect(resolve(PxUserService::class)->driver())->toBe('web')
            ->and(resolve(SessionDriver::class))->toBeInstanceOf(WebSessionDriver::class);
    });
});
