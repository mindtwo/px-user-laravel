<?php

namespace mindtwo\PxUserLaravel;

use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;
use mindtwo\PxUserLaravel\Http\Middleware\RefreshPxUserSession;
use mindtwo\PxUserLaravel\Scout\PxUserEngine;

class PxUserProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishConfig();
        $this->publishMigrations();

        resolve(EngineManager::class)->extend('px-user', function () {
            return new PxUserEngine;
        });

        $this->prioritizeRefreshMiddleware();
    }

    /**
     * Run RefreshPxUserSession before the authentication middleware wherever it is listed.
     *
     * Laravel sorts the authentication middleware ahead of SubstituteBindings in the web group,
     * so without this it would pass a route group's `auth` before the session ends.
     */
    protected function prioritizeRefreshMiddleware(): void
    {
        $this->callAfterResolving(HttpKernelContract::class, function (HttpKernelContract $kernel) {
            if ($kernel instanceof HttpKernel) {
                $kernel->addToMiddlewarePriorityBefore(AuthenticatesRequests::class, RefreshPxUserSession::class);
            }
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/px-user.php', 'px-user');
    }

    /**
     * Publish the config file.
     *
     * @return void
     */
    protected function publishConfig()
    {
        $configPath = __DIR__.'/../config/px-user.php';

        $this->publishes([
            $configPath => config_path('px-user.php'),
        ], 'px-user');

        $this->mergeConfigFrom($configPath, 'px-user');
    }

    /**
     * Publish the package migrations.
     */
    protected function publishMigrations(): void
    {
        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'px-user-migrations');
    }
}
