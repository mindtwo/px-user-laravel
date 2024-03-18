<?php

namespace mindtwo\PxUserLaravel\Facades;

use Illuminate\Support\Facades\Facade;
use mindtwo\PxUserLaravel\Driver\Contracts\SessionDriver;

/**
 * @method static \mindtwo\PxUserLaravel\Driver\Contracts\SessionDriver driver()
 * @method static \mindtwo\PxUserLaravel\Driver\Contracts\SessionDriver login(array $tokenData)
 * @method static \mindtwo\PxUserLaravel\Driver\Contracts\AccessTokenHelper newAccessTokenHelper(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static null|\mindtwo\PxUserLaravel\Driver\Contracts\AccessTokenHelper getAccessTokenHelper()
 * @method static null|\mindtwo\PxUserLaravel\Driver\Contracts\ExpirationHelper getExpirationHelper()
 * @method static string getTenant()
 * @method static string getDomain()
 * @method static bool validate()
 * @method static null|bool|array refresh(\Illuminate\Contracts\Auth\Authenticatable $user, ?string $refreshToken = null)
 * @method static bool logout()
 * @method static null|int|string userId()
 * @method static null|\Illuminate\Contracts\Auth\Authenticatable user()
 * @method static void setUser(\Illuminate\Contracts\Auth\Authenticatable $user)
 */
class PxUserSession extends Facade
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
        return SessionDriver::class;
    }
}
