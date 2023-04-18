<?php

namespace mindtwo\PxUserLaravel\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use mindtwo\PxUserLaravel\Actions\PxUserGetDetailsAction;
use mindtwo\PxUserLaravel\Actions\PxUserDataRefreshAction;
use mindtwo\PxUserLaravel\Events\PxUserLoginEvent;
use mindtwo\PxUserLaravel\Listeners\UserLoginListener;
use mindtwo\PxUserLaravel\Services\CheckUserTokenService;
use mindtwo\PxUserLaravel\Services\PxAdminClient;
use mindtwo\PxUserLaravel\Services\PxUserClient;
use mindtwo\PxUserLaravel\Services\UserDataService;

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

        Event::listen(
            PxUserLoginEvent::class,
            UserLoginListener::class,
        );
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/px-user.php', 'px-user');

        $this->app->singleton(PxAdminClient::class, function (Application $app) {
            return new PxAdminClient(config('px-user'));
        });

        $this->app->singleton(PxUserClient::class, function (Application $app) {
            return new PxUserClient(config('px-user'));
        });

        $this->app->singleton(CheckUserTokenService::class, function (Application $app) {
            $pxAdminClient = $app->make(PxAdminClient::class);

            return new CheckUserTokenService(
                $pxAdminClient,
            );
        });


        $this->app->singleton('UserDataCache', function (Application $app) {
            $pxUserClient = $app->make(PxUserClient::class);
            $checkUserTokenService = $app->make(CheckUserTokenService::class);

            return new UserDataService(
                new PxUserDataRefreshAction($pxUserClient, $checkUserTokenService),
                new PxUserGetDetailsAction($pxUserClient, $checkUserTokenService),
            );
        });
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
    }
}
