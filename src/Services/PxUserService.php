<?php

namespace mindtwo\PxUserLaravel\Services;

use Illuminate\Support\Facades\Http;
use mindtwo\PxUserLaravel\Cache\UserDataCache;

class PxUserService
{
    /**
     * Fake response.
     */
    private bool $fakes = false;

    public function fake(): self
    {
        if (! app()->environment('testing') || ! app()->runningUnitTests()) {
            throw new \RuntimeException('PxUserService::fake() can only be used in testing environment.');
        }

        Http::fake([
            'user.*.pl-x.cloud/v1/user' => function () {
                $user = auth()->user();

                if ($user === null) {
                    return Http::response(['error' => 'Unauthorized'], 401);
                }

                return Http::response($this->fakeUserData($user->{config('px-user.px_user_id')}), 200);
            },
        ]);

        $this->fakes = true;

        return $this;
    }

    public function isFaking(): bool
    {
        return $this->fakes;
    }

    public function get(string $pxUserId): array
    {
        if ($this->fakes) {
            return $this->fakeUserData($pxUserId);
        }

        $user = config('px-user.px_user_model')::where(config('px-user.px_user_id'), $pxUserId)->first();

        return (new UserDataCache($user))->toArray();
    }

    public function fakeUserData(?string $pxUserId, bool $withPermissions = false, array $overwrite = []): array
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
