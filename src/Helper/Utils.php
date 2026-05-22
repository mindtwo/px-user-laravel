<?php

namespace mindtwo\PxUserLaravel\Helper;

use mindtwo\PxUserLaravel\Contracts\PxUser;

readonly class Utils
{
    public static function getPxUserCacheKey(string|PxUser $user): string
    {
        $userId = $user instanceof PxUser ? $user->getPxUserId() : $user;

        return cache_key('px-user', [
            'class' => config('px-user.user_model'),
            'key' => $userId,
        ])->toString();
    }
}
