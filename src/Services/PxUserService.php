<?php

namespace mindtwo\PxUserLaravel\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use mindtwo\PxUserLaravel\Cache\AdminUserDataCache;
use mindtwo\PxUserLaravel\Cache\UserDataCache;
use mindtwo\PxUserLaravel\Driver\Contracts\SessionDriver;

class PxUserService
{
    /**
     * Fake response.
     */
    private bool $fakes = false;

    public function fake(): self
    {
        if (! app()->runningUnitTests()) {
            throw new \RuntimeException('PxUserService::fake() can only be used in testing environment.');
        }

        $url = sprintf(
            '%s/%s',
            rtrim(config('px-user.base_url'), '/'),
            'v1',
        );

        Http::fake([
            "$url/user" => function () {
                $user = auth()->user();

                if ($user === null) {
                    return Http::response(['error' => 'Unauthorized'], 401);
                }

                return Http::response([
                    'response' => [
                        'user' => $this->fakeUserData($user->{config('px-user.px_user_id')}),
                    ],
                ], 200);
            },
        ]);

        $this->fakes = true;

        return $this;
    }

    public function isFaking(): bool
    {
        return $this->fakes;
    }

    /**
     * Get recommended cache class. If running in console, use AdminUserDataCache, otherwise UserDataCache.
     *
     * @return class-string<DataCache>
     */
    public function getRecommendedCacheClass(): string
    {
        if ($this->fakes || app()->runningUnitTests()) {
            return UserDataCache::class;
        }

        return app()->runningInConsole() ? AdminUserDataCache::class : UserDataCache::class;
    }

    /**
     * Get recommended cache class instance.
     *
     * @param  Model  $user
     */
    public function getRecommendedCacheClassInstance($user): AdminUserDataCache|UserDataCache
    {
        return (new ($this->getRecommendedCacheClass()))($user);
    }

    /**
     * Get session driver for active auth guard.
     */
    public function session(?string $guard = null): ?SessionDriver
    {
        // If no guard is given, use the active guard, if that is not available, use the default guard.
        if ($guard === null) {
            $guard = active_guard(config('px-user.driver.default'));
        }

        $driverConfig = config("px-user.driver.$guard");
        if (! $driverConfig) {
            Log::error('PxUserLaravel: No driver found');

            return null;
        }

        $driverClass = $driverConfig['session_driver'];

        return app()->make($driverClass);
    }

    /**
     * Fake user data.
     */
    public function fakeUserData(?string $pxUserId, bool $withPermissions = false): array
    {
        $rolesKey = $withPermissions ? 'capabilities' : 'roles';
        $rolesValue = $withPermissions ? [
            'tenants' => [
                'testing' => [
                    'code' => 'testing',
                    'domains' => [
                        'px_teach' => [
                            'code' => 'px_teach',
                            'products' => [
                                'test' => [
                                    'roles' => [
                                        'admin',
                                    ],
                                    'permissions' => [
                                        'canCreateUser',
                                        'canDeleteUser',
                                    ],
                                ],
                                'test2' => [
                                    'roles' => [
                                        'student',
                                    ],
                                    'permissions' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ] : [
            'test' => [
                'admin',
            ],
            'test2' => [
                'student',
            ],
        ];

        return [
            'id' => $pxUserId ?? 'test',
            'email' => 'test@example.com',
            'tenant_code' => 'testing',
            'domain_code' => 'px_teach',
            'is_enabled' => true,
            'is_confirmed' => true,
            'firstname' => 'Jon',
            'lastname' => 'Doe',
            'last_login_at' => '',
            'products' => [
                'test', 'test2',
            ],
            $rolesKey => $rolesValue,
        ];
    }
}
