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

    /**
     * Get the product code sent as PX User product context.
     *
     * A deprecated `scout.product_code` left in a published config wins unless it is the stock `lms`.
     */
    public static function productCode(): string
    {
        $legacy = config('px-user.scout.product_code');

        if ($legacy !== null && $legacy !== 'lms') {
            return $legacy;
        }

        return config('px-user.product_code') ?? 'lms';
    }
}
