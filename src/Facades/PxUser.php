<?php

namespace mindtwo\PxUserLaravel\Facades;

use Illuminate\Support\Facades\Facade;
use mindtwo\PxUserLaravel\Services\FakePxUserService;

/**
 * TODO merge this and PxUserSession Facade
 *
 * @method static null|\mindtwo\PxUserLaravel\Driver\Contracts\SessionDriver session(?string $guard = null)
 */
class PxUser extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return \mindtwo\PxUserLaravel\Services\PxUserService::class;
    }

    /**
     * Replace the bound instance with a fake.
     */
    public static function fake(): FakePxUserService
    {
        if (! app()->runningUnitTests()) {
            throw new \RuntimeException('RedConnectApi::fake() can only be called in Pest tests.');
        }

        return tap(new FakePxUserService, static function ($fake) {
            static::swap($fake);
        });
    }
}
