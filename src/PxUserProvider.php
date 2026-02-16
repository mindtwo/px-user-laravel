<?php

namespace mindtwo\PxUserLaravel;

use Exception;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;
use mindtwo\PxUserLaravel\ExternalApiTokens\PxUserEloquentTokenRepository;
use mindtwo\PxUserLaravel\ExternalApiTokens\PxUserRedisTokenRepository;
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

        resolve(EngineManager::class)->extend('px-user', function () {
            return new PxUserEngine;
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

        // Configure external API token repository for PxUser
        $this->configureExternalApiTokens();
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
     * Configure external API token repository for PxUser.
     *
     * @return void
     */
    protected function configureExternalApiTokens()
    {
        $driver = config('px-user.token_driver', 'redis');

        // Merge PxUser API configuration into external-api config
        config([
            'external-api.apis.px-user' => [
                'repository' => 'px-user',
            ],
        ]);

        // Register appropriate repository based on driver
        $repositoryClass = match ($driver) {
            'eloquent' => PxUserEloquentTokenRepository::class,
            'redis' => PxUserRedisTokenRepository::class,
            default => throw new Exception('Invalid driver for px user.', 1),
        };

        config([
            'external-api.alias.px-user' => $repositoryClass,
        ]);
    }
}
