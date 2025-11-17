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
    protected array $config = [];

    protected ?string $activeDriver = null;

    /**
     * Create a new PxUserService instance.
     */
    public function __construct(
        ?string $driver = null,
    ) {
        if ($driver !== null) {
            $this->activeDriver = $driver;
        }
    }

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
     * Get session driver for active auth driver.
     */
    public function session(): ?SessionDriver
    {
        // Get the config for the specified driver
        $driverConfig = $this->config();

        if (! $driverConfig) {
            Log::error('PxUserLaravel: No driver found');

            return null;
        }

        $driverClass = $driverConfig['session_driver'];

        return app()->make($driverClass);
    }

    /**
     * Load configuration for the specified driver.
     */
    public function loadConfig(string $driver): self
    {
        // Set the active driver
        $this->activeDriver = $driver;
        config()->set('px-user.driver.default', $driver);

        // Load the configuration for the specified driver
        $this->config = config("px-user.driver.$driver");

        if (empty($this->config)) {
            Log::error("PxUserLaravel: No config found for driver '$driver'");

            return $this;
        }

        return $this;
    }

    /**
     * Get the active driver
     */
    public function driver(?string $fallback = null): ?string
    {
        if (isset($this->activeDriver)) {
            // If activeDriver is set, return it
            return $this->activeDriver;
        }

        return collect([
            config('px-user.driver.default'),
            config('auth.defaults.guard'),
            $fallback,
        ])->first(fn ($guard) => ! empty($guard)) ?: null;
    }

    /**
     * Get the configuration for the active driver.
     */
    public function config(): array
    {
        $driver = $this->driver();
        $config = config("px-user.driver.$driver", []);

        if (empty($config)) {
            Log::error("PxUserLaravel: No config found for driver '$driver'");
        }

        return $config;
    }
}
