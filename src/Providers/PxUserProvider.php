<?php

namespace mindtwo\PxUserLaravel\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use mindtwo\PxUserLaravel\Actions\PxUserGetDetailsAction;
use mindtwo\PxUserLaravel\Actions\PxUserDataRefreshAction;
use mindtwo\PxUserLaravel\Contracts\AccessTokenHelper as ContractsAccessTokenHelper;
use mindtwo\PxUserLaravel\Events\PxUserLoginEvent;
use mindtwo\PxUserLaravel\Listeners\UserLoginListener;
use mindtwo\PxUserLaravel\Sanctum\PersonalAccessToken;
use mindtwo\PxUserLaravel\Services\AccessTokenHelper;
use mindtwo\PxUserLaravel\Services\CheckUserTokenService;
use mindtwo\PxUserLaravel\Services\PxAdminClient;
use mindtwo\PxUserLaravel\Services\PxUserClient;
use mindtwo\PxUserLaravel\Services\SanctumAccessTokenHelper;
use mindtwo\PxUserLaravel\Services\UserDataService;
use mindtwo\PxUserLaravel\Services\WebAccessTokenHelper;

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

        if (config('px-user.sanctum.enabled') === true && class_exists(\Laravel\Sanctum\Sanctum::class)) {
            \Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

            \Laravel\Sanctum\Sanctum::authenticateAccessTokensUsing(function (PersonalAccessToken $accessToken, bool $isValid) {
                if (!$isValid) {
                    return false;
                }

                $accessTokenHelper = app()->makeWith(SanctumAccessTokenHelper::class, [
                    'user' => $accessToken->tokenable,
                ]);

                if ($accessTokenHelper->accessTokenExpired()) {
                    $accessToken->update([
                        'expires_at' => Carbon::now(),
                    ]);

                    return false;
                }

                return true;
            });
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

        $this->app->singleton(PxAdminClient::class, function (Application $app) {
            return new PxAdminClient(config('px-user'));
        });

        $this->app->singleton(PxUserClient::class, function (Application $app) {
            return new PxUserClient(config('px-user'));
        });

        $this->app->singleton(CheckUserTokenService::class, function (Application $app) {
            return new CheckUserTokenService();
        });

        $this->app->bind(ContractsAccessTokenHelper::class, function (Application $app) {
            if (auth()->check()) {
                return new WebAccessTokenHelper();
            }

            if (auth('sanctum')->check()) {
                return new SanctumAccessTokenHelper(
                    auth('sanctum')->user(),
                );
            }

            return new AccessTokenHelper();
        });

        $this->app->bind('AccessTokenHelper', function (Application $app) {
            return $app->make(ContractsAccessTokenHelper::class);
        });

        $this->app->singleton('UserDataCache', function (Application $app) {
            $pxUserClient = $app->make(PxUserClient::class);

            return new UserDataService(
                new PxUserDataRefreshAction($pxUserClient),
                new PxUserGetDetailsAction($pxUserClient),
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
