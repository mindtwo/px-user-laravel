<?php

namespace mindtwo\PxUserLaravel\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\ScoutServiceProvider;
use mindtwo\PxUserLaravel\PxUserProvider;
use mindtwo\TwoTility\TwoTilityProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelData\LaravelDataServiceProvider;

class TestCase extends Orchestra
{
    /**
     * Define environment setup.
     *
     * @param  Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        // Setup default database to use sqlite :memory:
        tap($app->make('config'), function (Repository $config) {
            $config->set('px-user.domain_code', 'testbench');
            $config->set('px-user.tenant_code', 'testbench');
            $config->set('px-user.m2m_credentials', 'test:secret');
        });
    }

    /**
     * Get package providers.
     *
     * @param  Application  $app
     * @return array<int, class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app)
    {
        return [
            ScoutServiceProvider::class,
            TwoTilityProvider::class,
            LaravelDataServiceProvider::class,
            PxUserProvider::class,
        ];
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(
            __DIR__.'/database/migrations',
        );
    }
}
