<?php

namespace mindtwo\PxUserLaravel\Tests;

use Illuminate\Contracts\Config\Repository;
use mindtwo\PxUserLaravel\Providers\PxUserProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        // Setup default database to use sqlite :memory:
        tap($app->make('config'), function (Repository $config) {
            $config->set('px-user.domain', 'testbench');
            $config->set('px-user.tenant', 'testbench');
            $config->set('px-user.m2m_credentials', 'test:secret');
        });
    }

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
            \mindtwo\PxApiClients\PxApiClientsServiceProvider::class,
        ];
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
