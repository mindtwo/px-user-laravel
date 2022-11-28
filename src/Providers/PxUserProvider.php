<?php

namespace mindtwo\PxUserLaravel\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use mindtwo\PxUserLaravel\Events\PxUserLoginEvent;
use mindtwo\PxUserLaravel\Listeners\UserLoginListener;
use mindtwo\PxUserLaravel\Services\PxUserClient;

class PxUserProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/px-user.php' => config_path('px-user.php'),
        ], 'px-user');

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
        $this->app->singleton(PxUserClient::class, function (Application $app) {
            return new PxUserClient(config('px-user'));
        });
    }
}
