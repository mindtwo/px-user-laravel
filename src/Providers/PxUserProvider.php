<?php

namespace mindtwo\PxUserLaravel\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;
use mindtwo\PxUserLaravel\Driver\Contracts\SessionDriver;
use mindtwo\PxUserLaravel\Http\Client\PxUserAdminClient;
use mindtwo\PxUserLaravel\Http\Client\PxUserClient;
use mindtwo\PxUserLaravel\Scout\PxUserEngine;
use mindtwo\PxUserLaravel\Services\PxUserService;

class PxUserProvider extends ServiceProvider
{
    private bool $sanctumIntegration;

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

        $this->sanctumIntegration = /* config('px-user.sanctum.enabled') === true && */ class_exists(\Laravel\Sanctum\Sanctum::class);

        if ($this->sanctumIntegration) {
            \Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(config('px-user.driver.sanctum.access_token_model'));
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/px-user.php', 'px-user');

        $this->app->scoped(PxUserService::class, function (Application $app, array $parameters = []) {
            // session method handles the retrieval of the driver
            $driver = $parameters['driver'] ?? config('px-user.driver.default');

            return new PxUserService($driver);
        });

        $this->app->scoped(PxUserAdminClient::class, function (Application $app) {
            return new PxUserAdminClient;
        });

        $this->app->scoped(PxUserClient::class, function (Application $app) {
            return new PxUserClient(
                tenantCode: config('px-user.tenant_code'),
                domainCode: config('px-user.domain_code'),
            );
        });

        $this->app->scoped('px-user-client', function (Application $app) {
            if (app()->runningInConsole() && ! app()->runningUnitTests()) {
                return new PxUserAdminClient(
                    tenantCode: config('px-user.tenant_code'),
                    domainCode: config('px-user.domain_code'),
                );
            }

            return $app->make(PxUserClient::class);
        });

        $this->app->bind(SessionDriver::class, function (Application $app, array $parameters = []) {
            // If PxUserService has already been resolved (e.g., by LoadPxUserDriver middleware),
            // reuse that instance to prevent overriding the driver configuration
            if ($app->resolved(PxUserService::class)) {
                return $app->make(PxUserService::class)->session();
            }

            // Otherwise, resolve with the provided driver parameter if available
            $driver = $parameters['driver'] ?? null;

            return $app->make(PxUserService::class, $driver ? ['driver' => $driver] : [])->session();
        });
    }

    /**
     * Publish the config file.
     *
     * @return void
     */
    protected function publishMigrations()
    {
        $this->publishes([
            __DIR__.'/../../database/migrations/update_personal_access_tokens_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_update_personal_access_tokens_table.php'),
        ], 'px-user');
    }

    /**
     * Publish the config file.
     *
     * @return void
     */
    protected function publishConfig()
    {
        $configPath = __DIR__.'/../../config/px-user.php';

        $this->publishes([
            $configPath => config_path('px-user.php'),
        ], 'px-user');

        $this->mergeConfigFrom($configPath, 'px-user');
    }
}
