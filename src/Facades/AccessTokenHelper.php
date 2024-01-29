<?php

namespace mindtwo\PxUserLaravel\Facades;

use Illuminate\Support\Facades\Facade;
use mindtwo\PxUserLaravel\Services\AccessTokenHelper as ServicesAccessTokenHelper;

/**
 * @method static bool accessTokenExpired()
 * @method static bool canRefresh()
 * @method static void saveTokenData(array $tokenData)
 * @method static void flush()
 * @method static array values()
 * @method static void put(string $key, string $value)
 * @method static mixed get(string $key)
 * @method static string allowed(string $key)
 * @method static array allowedKeys()
 */
class AccessTokenHelper extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    protected static function getFacadeAccessor()
    {
        return ServicesAccessTokenHelper::class;
    }
}
