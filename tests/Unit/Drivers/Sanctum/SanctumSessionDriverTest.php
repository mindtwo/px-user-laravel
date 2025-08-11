<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use mindtwo\PxUserLaravel\Facades\PxUserSession;
use mindtwo\PxUserLaravel\Tests\Fake\User;

beforeEach(function () {
    Config::set('px-user.driver.default', 'sanctum');

    $stage = config('px-user.stage', 'prod');

    $url = sprintf(
        '%s/%s',
        rtrim(config("px-api-clients.px-user.base_url.{$stage}", null), '/'),
        'v1',
    );

    Http::fake([
        "$url/refresh-tokens" => function () {
            $user = auth()->user();

            if ($user === null) {
                return Http::response(['error' => 'Unauthorized'], 401);
            }

            return Http::response([
                'response' => [
                    'access_token' => 'fake_access_token',
                    'access_token_expiration_utc' => now()->addMinutes(60)->toIso8601String(),
                    'refresh_token' => 'test-refresh-token',
                    'refresh_token_expiration_utc' => now()->addDays(30)->toIso8601String(),
                ],
            ], 200);
        },
    ]);
});

test('pxSession returns SanctumSessionDriver', function () {
    $this->assertInstanceOf(
        \mindtwo\PxUserLaravel\Driver\Sanctum\SanctumSessionDriver::class,
        PxUserSession::driver()
    );
});

test('get new refresh token', function () {
    $driver = PxUserSession::driver();
    Auth::login(User::factory()->create());
    $method = new ReflectionMethod(
        // Class , Method
        get_class($driver), 'getNewRefreshToken'
    );

    $method->setAccessible(true);

    $response = $method->invoke($driver, 'test');

    $this->assertIsArray($response);
    $this->assertArrayHasKey('access_token', $response);
    $this->assertArrayHasKey('refresh_token', $response);
});

test('get refresh token', function () {
    $user = User::factory()->create();

    $driver = PxUserSession::driver();
    $driver->setUser($user);

    Auth::login($user);

    $user->createAccessToken('test-token', null, 'test-refresh-token');

    $method = new ReflectionMethod(
        // Class , Method
        get_class($driver), 'refreshAccessToken'
    );

    $method->setAccessible(true);

    $result = $method->invoke($driver, 'test-refresh-token');

    $this->assertInstanceOf(\Laravel\Sanctum\NewAccessToken::class, $result);
    $this->assertEquals(1, $user->tokens()->count());
});

// test('route /api/v1/auth/refresh', function () {
//     $user = User::factory()->create();
//     $refreshToken = 'test-refresh-token';
//     Auth::login($user);

//     $user->createAccessToken('test-token', null, 'test-refresh-token');

//     $response = $this->postJson('/api/v1/refresh', [
//         'refreshToken' => $refreshToken,
//     ]);

//     $response->assertStatus(200);

//     $this->assertEquals(1, $user->tokens()->count());
//     $this->assertNotEquals($refreshToken, $user->tokens()->first()->refresh_token);

//     $this->assertNotNull($user->getPxUserToken());
//     $this->assertNotEquals('fake-token', Cache::get(cache_key(config('px-user.session_prefix') ?? 'px_user', [
//         $user->{config('px-user.px_user_id')},
//         'access_token',
//     ])->debugIf(config('px-user.debug'))->toString()));
// });
