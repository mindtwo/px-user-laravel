<?php

namespace mindtwo\PxUserLaravel\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
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

    protected function debug(string $message, array $context = []): void
    {
        if (! config('px-user.debug')) {
            return;
        }

        Log::debug('PxUserLaravel: '.$message, $context);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/px-user.php', 'px-user');

        $this->app->scoped(PxUserService::class, function (Application $app) {
            // session method handles the retrieval of the guard
            $guard = $parameters['guard'] ?? config('px-user.driver.default');

            return new PxUserService($guard);
        });

        $this->app->scoped(PxUserAdminClient::class, function (Application $app) {
            return new PxUserAdminClient;
        });

        $this->app->scoped(PxUserClient::class, function (Application $app) {
            return new PxUserClient(
                tenantCode: config('px-user.tenant'),
                domainCode: config('px-user.domain'),
            );
        });

        $this->app->scoped('px-user-client', function (Application $app) {
            if (app()->runningInConsole() && ! app()->runningUnitTests()) {
                return new PxUserAdminClient(
                    tenantCode: config('px-user.tenant'),
                    domainCode: config('px-user.domain'),
                );
            }

            return $app->make(PxUserClient::class);
        });

        $this->app->scoped(SessionDriver::class, function (Application $app, array $parameters = []) {
            // session method handles the retrieval of the guard
            $guard = $parameters['guard'] ?? config('px-user.driver.default');

            return $app->make(PxUserService::class)->session($guard);
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
