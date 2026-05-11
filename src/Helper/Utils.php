<?php

namespace mindtwo\PxUserLaravel\Helper;

use mindtwo\PxUserLaravel\Contracts\PxUser;

readonly class Utils
{
    public static function getPxUserCacheKey(string|PxUser $user, string $name = 'px-user'): string
    {
        $userId = $user instanceof PxUser ? $user->getPxUserId() : $user;

        return cache_key($name, [
            'class' => config('px-user.user_model'),
            'key' => $userId,
        ])->toString();
    }
}
