<?php

namespace mindtwo\PxUserLaravel\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use mindtwo\PxUserLaravel\Actions\PxUserDataRefreshAction;
use mindtwo\PxUserLaravel\Actions\PxUserGetDetailsAction;
// use mindtwo\PxUserLaravel\Contracts\AccessTokenHelper as ContractsAccessTokenHelper;
use mindtwo\PxUserLaravel\Events\PxUserLoginEvent;
use mindtwo\PxUserLaravel\Events\PxUserTokenRefreshEvent;
use mindtwo\PxUserLaravel\Http\PxAdminClient;
use mindtwo\PxUserLaravel\Http\PxUserClient;
use mindtwo\PxUserLaravel\Listeners\UserLoginListener;
use mindtwo\PxUserLaravel\Listeners\UserTokenRefreshListener;
use mindtwo\PxUserLaravel\Services\AccessTokenHelper;
use mindtwo\PxUserLaravel\Services\CheckUserTokenService;
use mindtwo\PxUserLaravel\Services\PxUserService;
use mindtwo\PxUserLaravel\Services\UserDataService;

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

        Event::listen(
            PxUserLoginEvent::class,
            UserLoginListener::class,
        );

        Event::listen(
            PxUserTokenRefreshEvent::class,
            UserTokenRefreshListener::class,
        );

        $this->sanctumIntegration = config('px-user.sanctum.enabled') === true && class_exists(\Laravel\Sanctum\Sanctum::class);
        if ($this->sanctumIntegration) {
            \Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(config('px-user.sanctum.access_token_model'));

            // TODO
            // \Laravel\Sanctum\Sanctum::authenticateAccessTokensUsing(function ($accessToken, bool $isValid) {
            //     $this->debug('Sanctum::authenticateAccessTokensUsing@1', [
            //         'accessToken' => $accessToken,
            //         'isValid' => $isValid,
            //         'tokenable' => $accessToken->tokenable,
            //         'abort' => ! $isValid || $accessToken->tokenable === null,
            //     ]);

            //     if (! $isValid || $accessToken->tokenable === null) {
            //         return false;
            //     }

            //     $this->debug('Sanctum::authenticateAccessTokensUsing@accessTokenHelperInit', [
            //         'tokenable' => $accessToken->tokenable,
            //     ]);

            //     $accessTokenHelper = new AccessTokenHelper($accessToken->tokenable);

            //     $this->debug('Sanctum::authenticateAccessTokensUsing@accessTokenHelperInitialized', [
            //         'user' => $accessTokenHelper->user,
            //         'accessTokenExpired' => $accessTokenHelper->accessTokenExpired(),
            //         'canRefresh' => $accessTokenHelper->canRefresh(),
            //         'abort' => $accessTokenHelper->accessTokenExpired() && ! $accessTokenHelper->canRefresh(),
            //     ]);

            //     // invalidate personal access token from sanctum if user token is expired
            //     if ($accessTokenHelper->accessTokenExpired() && ! $accessTokenHelper->canRefresh()) {
            //         $this->debug('Sanctum::authenticateAccessTokensUsing@expireToken');

            //         $accessToken->update([
            //             'expires_at' => Carbon::now(),
            //         ]);

            //         return false;
            //     }

            //     return true;
            // });
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

        $this->app->singleton('px-user', function (Application $app) {
            return new PxUserService();
        });

        // TODO remove from here
        $this->app->singleton(PxAdminClient::class, function (Application $app) {
            return new PxAdminClient(...config('px-user'));
        });

        // TODO remove from here
        $this->app->singleton(PxUserClient::class, function (Application $app) {
            return new PxUserClient(...config('px-user'));
        });

        $this->app->singleton(CheckUserTokenService::class, function (Application $app) {
            return new CheckUserTokenService();
        });

        $this->app->bind(AccessTokenHelper::class, function (Application $app) {
            return new AccessTokenHelper(Auth::user());
        });

        // TODO remove entire class
        $this->app->singleton(UserDataService::class, function (Application $app) {
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
    }
}
