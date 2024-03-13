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

        $this->booting(function () {
            $test = auth()->guard();

            Log::debug('PxUserProvider: booting', [
                'guard' => $test,
            ]);
        });

        $this->sanctumIntegration = /* config('px-user.sanctum.enabled') === true && */ class_exists(\Laravel\Sanctum\Sanctum::class);

        if ($this->sanctumIntegration) {
            \Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(config('px-user.sanctum.access_token_model'));
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

        $this->app->scoped('px-user', function (Application $app) {
            return new PxUserService();
        });

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

        $this->app->bind(SessionDriver::class, function () {
            return app('px-user')->session();
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
