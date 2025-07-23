<?php

namespace mindtwo\PxUserLaravel\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use mindtwo\PxUserLaravel\Cache\AdminUserDataCache;
use mindtwo\PxUserLaravel\Cache\UserDataCache;
use mindtwo\PxUserLaravel\Driver\Contracts\SessionDriver;
use mindtwo\PxUserLaravel\Services\FakePxUserService;
use mindtwo\TwoTility\Cache\Data\DataCache;

/**
 * TODO merge this and PxUserSession Facade
 *
 * @method static null|SessionDriver session(?string $guard = null)
 * @method static class-string<DataCache> getRecommendedCacheClass(?Model $user)
 * @method static UserDataCache|AdminUserDataCache getRecommendedCacheClassInstance(Model $user)
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
