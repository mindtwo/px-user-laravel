<?php

use Illuminate\Support\Facades\Config;
use mindtwo\PxUserLaravel\Facades\PxUser;
use mindtwo\PxUserLaravel\Facades\PxUserSession;

beforeEach(function () {
    Config::set('px-user.driver.default', 'sanctum');
});

test('pxSession returns SanctumSessionDriver', function () {
    $this->assertInstanceOf(
        \mindtwo\PxUserLaravel\Driver\Sanctum\SanctumSessionDriver::class,
        PxUserSession::driver()
    );
});

test('get new refresh token', function () {
    PxUser::fake();

    $driver = PxUserSession::driver();

    $method = new ReflectionMethod(
        // Class , Method
        get_class($driver), 'getNewRefreshToken'
    );

    $method->setAccessible(true);

    $this->assertEquals(
        // (Object [, mixed $parameter [, mixed $... ]])
        'blah', $method->invoke($driver, 'test')
    );
});
