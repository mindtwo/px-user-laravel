<?php

namespace mindtwo\PxUserLaravel\Tests;

use mindtwo\PxUserLaravel\Facades\AccessTokenHelper;
use mindtwo\PxUserLaravel\Facades\UserDataCache;
use mindtwo\PxUserLaravel\Providers\PxUserProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app)
    {
        return [
            PxUserProvider::class,
        ];
    }

    /**
     * Override application aliases.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<string, class-string<\Illuminate\Support\Facades\Facade>>
     */
    protected function getPackageAliases($app)
    {
        return [
            'UserDataCache' => UserDataCache::class,
            'AccessTokenHelper' => AccessTokenHelper::class,
        ];
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        // dd(__DIR__ . '/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadLaravelMigrations();
    }
}
