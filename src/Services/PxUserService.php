<?php

namespace mindtwo\PxUserLaravel\Services;

use mindtwo\PxUserLaravel\Cache\UserDataCache;

class PxUserService
{
    /**
     * Fake response.
     */
    private bool $fakes = false;

    public function fake(): self
    {
        $this->fakes = true;

        return $this;
    }

    public function get(string $pxUserId): array
    {
        if ($this->fakes) {
            return [
                'id' => $pxUserId,
                'email' => 'test@example.com',
                'tenant_code' => 'testing',
                'domain_code' => 'px_teach',
                'is_enabled' => true,
                'is_confirmed' => true,
                'firstname' => 'Jon',
                'lastname' => 'Doe',
                'last_login_at' => '',
                'roles' => [
                    'test' => [
                        'admin',
                    ],
                    'test2' => [
                        'student',
                    ],
                ],
                'products' => [
                    'test', 'test2',
                ],
            ];
        }

        $user = config('px-user.px_user_model')::where(config('px-user.px_user_id'), $pxUserId)->first();
        return (new UserDataCache($user))->toArray();
    }
}
