<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use mindtwo\PxUserLaravel\Http\Middleware\CheckPxUserSession;
use mindtwo\PxUserLaravel\Tests\Fake\User;

beforeEach(function () {
    config(['px-user.user_model' => User::class]);
    config(['px-user.px_user_id' => 'px_user_id']);
});

test('middleware allows request to pass through', function () {
    $middleware = new CheckPxUserSession;
    $request = Request::create('/test', 'GET');

    $response = $middleware->handle($request, function ($req) {
        return response('OK');
    });

    expect($response->getContent())->toBe('OK');
});

test('middleware handles authenticated PxUser', function () {
    $user = User::factory()->create(['px_user_id' => 'test-user-123']);
    Auth::login($user);

    $middleware = new CheckPxUserSession;
    $request = Request::create('/test', 'GET');

    // Set the user resolver on the request
    $request->setUserResolver(fn () => $user);

    $response = $middleware->handle($request, function ($req) {
        return response('OK');
    });

    expect($response->getContent())->toBe('OK');
});

test('middleware handles unauthenticated request', function () {
    $middleware = new CheckPxUserSession;
    $request = Request::create('/test', 'GET');

    $response = $middleware->handle($request, function ($req) {
        return response('OK');
    });

    expect($response->getContent())->toBe('OK');
});

test('middleware accepts optional driver parameter', function () {
    $middleware = new CheckPxUserSession;
    $request = Request::create('/test', 'GET');

    $response = $middleware->handle($request, function ($req) {
        return response('OK');
    }, 'custom-driver');

    expect($response->getContent())->toBe('OK');
});
