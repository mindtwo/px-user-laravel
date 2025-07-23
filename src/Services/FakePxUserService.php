<?php

namespace mindtwo\PxUserLaravel\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Testing\Fakes\Fake;
use mindtwo\PxUserLaravel\Cache\UserDataCache;
use mindtwo\PxUserLaravel\Cache\UserDetailDataCache;
use mindtwo\PxUserLaravel\Driver\Contracts\SessionDriver;
use mindtwo\TwoTility\Cache\Data\DataCache;

class FakePxUserService implements Fake
{
    public function __construct()
    {
        $this->registerHttpFakes();
    }

    /**
     * Get recommended cache class. If running in console, use AdminUserDataCache, otherwise UserDataCache.
     *
     * @param  ?Model  $user
     * @return class-string<DataCache>
     */
    public function getRecommendedCacheClass($user): string
    {
        if (! Auth::hasUser() || ! $user?->getKey()) {
            return UserDataCache::class;
        }

        return Auth::user() && $user->getKey() === Auth::user()->id ? UserDataCache::class : UserDetailDataCache::class;
    }

    /**
     * Get recommended cache class instance.
     *
     * @param  Model  $user
     */
    public function getRecommendedCacheClassInstance($user): UserDataCache
    {
        $clz = $this->getRecommendedCacheClass($user);

        if (! is_a($clz, UserDataCache::class, true)) {
            throw new \RuntimeException('PxUserService::getRecommendedCacheClassInstance() returned an invalid class');
        }

        return new $clz($user);
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

    protected function registerHttpFakes(): self
    {
        if (! app()->runningUnitTests()) {
            throw new \RuntimeException('PxUserService::fake() can only be used in testing environment.');
        }

        $stage = config('px-user.stage', 'prod');

        $url = sprintf(
            '%s/%s',
            rtrim(config("px-api-clients.px-user.base_url.{$stage}", null), '/'),
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

        Http::fake([
            "$url/users/details" => function () {
                $user = auth()->user();

                if ($user === null) {
                    return Http::response(['error' => 'Unauthorized'], 401);
                }

                return Http::response([
                    'response' => [
                        $this->fakeUserData($user->{config('px-user.px_user_id')}),
                    ],
                ], 200);
            },
        ]);

        Http::fake([
            "$url/refresh-tokens" => function () {
                $user = auth()->user();

                if ($user === null) {
                    return Http::response(['error' => 'Unauthorized'], 401);
                }

                return Http::response([
                    'response' => [
                        'access_token' => 'fake_access_token',
                        'expires_in' => 3600,
                    ],
                ], 200);
            },
        ]);

        return $this;
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
