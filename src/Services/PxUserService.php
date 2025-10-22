<?php

namespace mindtwo\PxUserLaravel\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use mindtwo\PxUserLaravel\Cache\AdminUserDataCache;
use mindtwo\PxUserLaravel\Cache\UserDataCache;
use mindtwo\PxUserLaravel\Cache\UserDetailDataCache;
use mindtwo\PxUserLaravel\Driver\Contracts\SessionDriver;
use mindtwo\TwoTility\Cache\Data\DataCache;

class PxUserService
{
    /**
     * Get recommended cache class. If running in console, use AdminUserDataCache, otherwise UserDataCache.
     *
     * @param  ?Model  $user
     * @return class-string<DataCache>
     */
    public function getRecommendedCacheClass($user): string
    {
        if (app()->runningUnitTests()) {
            throw new \RuntimeException('PxUserService::getRecommendedCacheClass() should not be called in unit tests');
        }

        if (app()->runningInConsole()) {
            return AdminUserDataCache::class;
        }

        if (! Auth::hasUser() || ! $user?->getKey()) {
            return UserDataCache::class;
        }

        return Auth::user() && $user->getKey() === Auth::user()->id ? UserDataCache::class : UserDetailDataCache::class;
    }

    /**
     * Get recommended cache class instance.
     *
     * @param  Model  $user
     */
    public function getRecommendedCacheClassInstance($user): AdminUserDataCache|UserDataCache
    {
        $clz = $this->getRecommendedCacheClass($user);

        if (! is_a($clz, UserDataCache::class, true)) {
            throw new \RuntimeException('PxUserService::getRecommendedCacheClassInstance() returned an invalid class');
        }

        return new $clz($user);
    }

    /**
     * Get session driver for active auth guard.
     */
    public function session(?string $guard = null): ?SessionDriver
    {
        // If no guard is given, use the active guard, if that is not available, use the default guard.
        if ($guard === null) {
            $guard = $this->activeGuard(config('px-user.driver.default'));
        }

        $driverConfig = config("px-user.driver.$guard");
        if (! $driverConfig) {
            Log::error('PxUserLaravel: No driver found');

            return null;
        }

        $driverClass = $driverConfig['session_driver'];

        return app()->make($driverClass);
    }

    /**
     * Get the active auth guard
     */
    public function activeGuard(?string $default = null): ?string
    {
        return config('auth.defaults.guard', $default);
    }
}
