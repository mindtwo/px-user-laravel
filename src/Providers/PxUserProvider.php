<?php

namespace mindtwo\PxUserLaravel\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use mindtwo\PxUserLaravel\Driver\Contracts\SessionDriver;
use mindtwo\PxUserLaravel\Http\Client\PxAdminClient;
use mindtwo\PxUserLaravel\Http\Client\PxClient;
use mindtwo\PxUserLaravel\Http\Client\PxUserClient;
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
            return new PxUserService();
        });
        $this->app->alias(PxUserService::class, 'px-user');

        $this->app->scoped(PxClient::class, function (Application $app) {
            if (app()->runningInConsole() && ! app()->runningUnitTests()) {
                return new PxAdminClient(
                    tenantCode: config('px-user.tenant'),
                    domainCode: config('px-user.domain'),
                    baseUrl: config('px-user.base_url'),
                );
            }

            return new PxUserClient(
                tenantCode: config('px-user.tenant'),
                domainCode: config('px-user.domain'),
                baseUrl: config('px-user.base_url'),
            );
        });

        $this->app->bind(SessionDriver::class, function (Application $app) {
            // session method handles the retrieval of the guard
            return $app->make('px-user')->session();
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
